<?php

declare(strict_types = 1);

namespace OurEdu\TokenClaims;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use OurEdu\TokenClaims\Middleware\PermissionMiddleware;
use OurEdu\TokenClaims\Middleware\RoleMiddleware;

class TokenClaimsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/token-claims.php', 'token-claims');

        // Scoped: one resolver, and so one IAM call, per request (Octane safe)
        $this->app->scoped(TokenClaimsResolver::class);
        $this->app->scoped(PermissionAuthorizer::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'token-claims');

        // A service whose HTTP Kernel still maps these to its own classes overrides them
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/token-claims.php' => config_path('token-claims.php'),
            ], 'token-claims-config');

            $this->publishes([
                __DIR__ . '/../lang' => $this->app->langPath() . '/vendor/token-claims',
            ], 'token-claims-lang');
        }
    }
}
