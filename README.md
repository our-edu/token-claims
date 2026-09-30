# our-edu/token-claims

Resolves the IAM token claims and permissions of the current request for
OurEdu Laravel services, and ships the `role` and `permission` middleware and
the 401 / 403 / 503 responses built on them. Each IAM answer is fetched once
per request (scoped bindings, Octane safe).

Supports PHP 8.1+ and Laravel 9, 10 and 11.

## Install

```bash
composer require our-edu/token-claims
```

The service provider is auto-discovered. It reads the IAM base URL from
`config('app.iam_service_url')`; set `TOKEN_CLAIMS_IAM_URL` to override it.

If your HTTP Kernel maps `role` or `permission` to local classes, point them at the package:

```php
'role' => \OurEdu\TokenClaims\Middleware\RoleMiddleware::class,
'permission' => \OurEdu\TokenClaims\Middleware\PermissionMiddleware::class,
```

## Usage

```php
use OurEdu\TokenClaims\ClaimsFailure;
use OurEdu\TokenClaims\TokenClaimsResolver;

$resolver = app(TokenClaimsResolver::class);

$resolver->claims();         // ?TokenClaims, null on any failure
$resolver->failure();        // ?ClaimsFailure: MissingToken | Rejected | Unavailable
$resolver->requireClaims();  // TokenClaims, or throws the 401 / 503 response

$claims = $resolver->requireClaims();
$claims->user_uuid;
$claims->role_name;
$claims->user_branches;
$claims->raw('some_new_iam_field');
```

The `TokenClaims` facade and `token_claims()` helper reach the same resolver:
`TokenClaims::requireClaims()`, `token_claims()->optionalClaims()` (null only
when the request has no token; other failures throw 401 / 503).

Routes:

```php
Route::get('/grades', ...)->middleware('role:teacher|admin');
Route::get('/classrooms', ...)->middleware('permission:classrooms.index|classrooms.show');
```

Permissions in code: `iam_can('classrooms', 'index')`. A denial is `false`;
IAM being unreachable or erroring throws the 503 instead of passing as a denial.

## Session model

Give the service's session model the trait and bind it per request:

```php
use OurEdu\TokenClaims\Concerns\HasTokenClaims;
use OurEdu\TokenClaims\TokenClaims;

class UserSession extends Model
{
    use HasTokenClaims;

    // Optional: map service-specific claims
    protected function fillExtraFromTokenClaims(TokenClaims $claims): void
    {
        $this->timezone = $claims->timezone;
    }
}

// AppServiceProvider::register()
\OurEdu\TokenClaims\Facades\TokenClaims::bindSession(UserSession::class);
```

`app(UserSession::class)` is then null without claims, otherwise filled with
`user_uuid`/`user_id`, `role_uuid`/`role_id`, `role_name`, `branch_uuid`,
`user_branches`, `check_branch`, `academic_year_uuid`, `is_valid`, `tenant_id`
and `branch_educational_systems`.

## Testing your service

```php
use OurEdu\TokenClaims\ClaimsFailure;
use OurEdu\TokenClaims\Facades\TokenClaims;

TokenClaims::fake(['role_name' => 'teacher']);          // no IAM call, no token needed
TokenClaims::fakeFailure(ClaimsFailure::Unavailable);    // every request gets 503
TokenClaims::fakePermissions(['classrooms.index']);      // ['*'] allows everything
```

The middleware still checks the auth guard, so keep `actingAs()` in HTTP tests.

| Case | Status | `title` |
|---|---|---|
| No token, IAM refused the token, incomplete claims | 401 | `invalid_session` |
| IAM unreachable, timed out or 5xx (claims or permissions) | 503 | `session_service_unavailable` |
| Guest, role not allowed, branch not allowed, permission denied | 403 | `unauthorized_action` |

Errors use the body `{"errors":[{"status","title","detail"}]}`.

## Configuration

```bash
php artisan vendor:publish --tag=token-claims-config
php artisan vendor:publish --tag=token-claims-lang
```

`messages.*` in the config are translation keys, so a service can point them at
its own strings (e.g. `auth.invalid_session`). The permission 403 names the
required permissions when `show_permissions_in_error` (or, when that is null,
`permission.display_permission_in_exception`) is true.

## Tests

```bash
composer install
vendor/bin/phpunit
```
