# our-edu/token-claims

Resolves the IAM token claims of the current request for OurEdu Laravel
services, and ships the `role` middleware and the 401 / 503 responses built on
them. Claims are fetched once per request (scoped binding, Octane safe).

Supports PHP 8.1+ and Laravel 9, 10 and 11.

## Install

```bash
composer require our-edu/token-claims
```

The service provider is auto-discovered. It reads the IAM base URL from
`config('app.iam_service_url')`; set `TOKEN_CLAIMS_IAM_URL` to override it.

If your HTTP Kernel maps `role` to a local class, point it at the package:

```php
'role' => \OurEdu\TokenClaims\Middleware\RoleMiddleware::class,
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

Routes:

```php
Route::get('/grades', ...)->middleware('role:teacher|admin');
```

| Case | Status | `title` |
|---|---|---|
| No token, IAM refused the token, incomplete claims | 401 | `invalid_session` |
| IAM unreachable, timed out or 5xx | 503 | `session_service_unavailable` |
| Guest, role not allowed, branch not allowed | 403 | `unauthorized_action` |

Errors use the body `{"errors":[{"status","title","detail"}]}`.

## Configuration

```bash
php artisan vendor:publish --tag=token-claims-config
php artisan vendor:publish --tag=token-claims-lang
```

`messages.*` in the config are translation keys, so a service can point them at
its own strings (e.g. `auth.invalid_session`).

## Tests

```bash
composer install
vendor/bin/phpunit
```
