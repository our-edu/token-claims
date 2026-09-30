# `our-edu/token-claims` package — design

Date: 2026-09-30
Status: approved design, pending spec review

## Problem

14 backend services each carry their own copy of the IAM token-claims logic
(`TokenClaimSingleton`, `TokenClaimsResponse`) and 15 carry a `RoleMiddleware`.
The copies have drifted: 9 distinct singletons, 13 distinct DTOs. Only
communication-be has the hardened version (fetch once per request, failure
reasons, timeout, response validation, 401/503 responses); every other service
silently yields a null session when IAM fails.

## Goal

One Composer package owns the token-claims client, the role middleware and the
401/503 handling. Services install it, delete their local copies, and receive
IAM-related fixes through a version bump. Services migrate one at a time.

### Out of scope

- Each service's `UserSession` model and its relations (`->user`, `->role`)
  stay in the service; the service maps package claims onto it.
- saas-iam-service (its own 54-line variant; IAM calling itself over HTTP needs
  separate review) and gateway (has `RoleMiddleware`, no claims singleton).
  Both may adopt the package later.
- Changing the guest response from 403 to 401.

## Distribution

- Repo: `github.com/our-edu/token-claims`, installed as a Composer VCS
  repository, same as `our-edu/multi-tenant`.
- Package name `our-edu/token-claims`, namespace `OurEdu\TokenClaims`.
- Semver tags; first release `v1.0.0`. Services require `^1.0`.
- Requirements: PHP `^8.1`, `illuminate/*` `^9.0|^10.0|^11.0`. No Spatie
  dependency.

## Package layout

```
config/token-claims.php
lang/en/token-claims.php
lang/ar/token-claims.php
src/
  TokenClaims.php
  TokenClaimsResolver.php
  ClaimsFailure.php
  Middleware/RoleMiddleware.php
  TokenClaimsServiceProvider.php
tests/
```

### `config/token-claims.php`

| Key | Default | Purpose |
|---|---|---|
| `iam_url` | `config('app.iam_service_url')`, else `env('IAM_SERVICE_URL')` | Base URL; trailing `/` trimmed, then `/token/claims` appended. Reading the service's existing `app.iam_service_url` keeps each service's current default (they differ: `:7777/iam/api/v1/`, `:80/iam/api/v1`, `:9000/api/v1`) |
| `timeout` | `10` | Seconds for the IAM HTTP call |
| `guard` | `null` | Guard checked by the middleware when the route passes none |
| `messages.invalid_session` | `'token-claims::token-claims.invalid_session'` | Lang key for 401 detail |
| `messages.service_unavailable` | `'token-claims::token-claims.session_service_unavailable'` | Lang key for 503 detail |
| `messages.unauthorized_action` | `'token-claims::token-claims.unauthorized_action'` | Lang key for 403 detail |

Services that already have these strings can point the keys at their own
translations (e.g. `auth.invalid_session`).

### `TokenClaims` (DTO, replaces `TokenClaimsResponse`)

Readonly properties built from IAM's `data` payload; the superset of fields
used across all current copies:

| Property | Source key | Type / default |
|---|---|---|
| `user_uuid` | `user_uuid` | `string` (required) |
| `role_name` | `role_name` | `string` (required) |
| `role_uuid` | `role_uuid` | `?string` |
| `branch_uuid` | `branch` | `?string` |
| `check_branch` | derived: `branch` set and `!== '*'` | `bool` |
| `user_branches` | `user_branches` | `array`, default `[]` |
| `academic_year_uuid` | `academic_year_uuid` | `?string` |
| `is_valid` | `is_valid` | `bool`, default `false` |
| `is_active` | `is_active` | `bool`, default `false` |
| `tenant_id` | `tenant_id` | `?int` |
| `branch_educational_systems` | `branch_educational_systems` | `array`, default `[]` |
| `user_educational_systems` | `user_educational_systems` | `array`, default `[]` |

Property names match the existing DTOs so service mapping code is unchanged
apart from the class name. `raw(string $key, mixed $default = null): mixed`
returns any key from the payload, so a service can read a new IAM field
before the package types it. `toArray(): array` returns the payload.

### `ClaimsFailure` (backed enum)

`MissingToken = 'missing_token'`, `Rejected = 'rejected'`,
`Unavailable = 'unavailable'`.

### `TokenClaimsResolver`

Bound **scoped** by the service provider (one instance per request; Octane
safe). Resolves once and caches the outcome, including failures, so a request
never calls IAM twice.

- `claims(): ?TokenClaims` — null on any failure, including a missing token.
- `failure(): ?ClaimsFailure` — why `claims()` is null; null on success.
- `hasClaims(): bool`
- `requireClaims(): TokenClaims` — returns claims or throws the 401/503
  `HttpResponseException` described below. Throws 401 for a missing token too.

Fetch rules:

1. No Bearer token → `MissingToken`. No HTTP call.
2. `GET {iam_url}/token/claims` with `Authorization: Bearer <token>` and the
   configured timeout.
3. Exception (connection error, timeout) → `Unavailable`; log error.
4. 5xx → `Unavailable`; 4xx → `Rejected`; log status and body.
5. 2xx whose `data` is not an array or lacks `user_uuid` / `role_name` →
   `Rejected`; log body.
6. Otherwise → `new TokenClaims($data)`.

Every log entry includes `service => config('app.name')` and the URL.

### `Middleware\RoleMiddleware`

Registered by the provider under the alias `role`. Signature unchanged:
`role:teacher|student[,guard]`. Reads `TokenClaimsResolver`, not the
service's `UserSession`.

1. `auth($guard ?? config('token-claims.guard'))->guest()` → 403
   `unauthorized_action` (preserves today's Spatie `notLoggedIn()` status).
2. `requireClaims()` → 401 / 503 on failure.
3. `role_name` not in the allowed roles (array or `|`-split) → 403.
4. `check_branch` and `user_branches` has neither `'*'` nor `branch_uuid` → 403.

### Error responses

All thrown as `Illuminate\Http\Exceptions\HttpResponseException` with the body
services already return:

```json
{"errors":[{"status":401,"title":"invalid_session","detail":"<translated>"}]}
```

| Case | Status | Title |
|---|---|---|
| Missing token, `Rejected` | 401 | `invalid_session` |
| `Unavailable` | 503 | `session_service_unavailable` |
| Guest, role denied, branch denied | 403 | `unauthorized_action` |

### `TokenClaimsServiceProvider`

Auto-discovered via `extra.laravel.providers`. Merges and publishes config
(`token-claims-config` tag), loads and publishes translations
(`token-claims::`), binds `TokenClaimsResolver` scoped, and registers the
`role` middleware alias on the router. A service that already maps `role` in
its HTTP Kernel must repoint that entry at the package middleware (Kernel
aliases take precedence).

## Service integration

Per service:

1. Add the VCS repository and `composer require our-edu/token-claims:^1.0`.
2. Delete the local `TokenClaimSingleton`, `TokenClaimsResponse`,
   `RoleMiddleware`, and their container bindings.
3. Point `'role'` in `Http/Kernel.php` at `OurEdu\TokenClaims\Middleware\RoleMiddleware`.
4. Rebuild the scoped `UserSession` binding from
   `app(TokenClaimsResolver::class)->claims()` (null → null).
5. `getSession()` returns the `UserSession` when claims resolve; returns null
   for `MissingToken` (jobs, commands, public routes); otherwise calls
   `requireClaims()` to throw 401/503. This is the behaviour communication-be
   has today.
6. Before switching, diff the service's local copy against the package; any
   service-only field or behaviour is added to the package or read via `raw()`.
7. Add a smoke test that valid claims map onto the service's `UserSession`.

The PR for each service other than communication-be states that IAM failures
now return 401/503 instead of proceeding with a null session.

## Testing

In the package (Orchestra Testbench + PHPUnit, CI matrix Laravel 9/10/11,
`Http::fake()`):

- Resolver: missing token (no HTTP call), 4xx, 5xx, connection exception,
  2xx missing `user_uuid`, 2xx missing `role_name`, non-array `data`, valid
  claims, one HTTP call for repeated `claims()` calls including after failure,
  fresh resolution per request scope.
- DTO: defaults, `check_branch` derivation, `raw()`.
- Middleware: guest → 403, allowed role, `|`-separated roles, denied role,
  branch `*`, matching branch, wrong branch, IAM failure → 401 / 503.
- Error body shape and translated `detail` in `en` and `ar`.

Communication-be's `tests/Unit/BaseApp/GetSessionTest.php` cases move into the
package; communication-be keeps the `UserSession` mapping smoke test.

## Rollout

1. Create `our-edu/token-claims`, port communication-be's implementation and
   tests, tag `v1.0.0`.
2. Pilot: communication-be. Its current uncommitted token-claims changes are
   superseded by this migration.
3. certificates-be, canteen-be, new-assessment-be, new-payment-be (identical
   copies).
4. One PR each: admission-be, automatic-call-be (Laravel 9),
   general-quizes-be, general-quizzes-answers-be, ouredu-dashboardbe,
   sessions-be, transportation-be, website-be.
