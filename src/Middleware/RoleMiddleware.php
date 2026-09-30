<?php

declare(strict_types = 1);

namespace OurEdu\TokenClaims\Middleware;

use Closure;
use Illuminate\Http\Request;
use OurEdu\TokenClaims\ErrorResponse;
use OurEdu\TokenClaims\TokenClaimsResolver;

/**
 * Usage: ->middleware('role:teacher|student') or 'role:teacher,api'.
 * Checks the IAM claims' role, and the active branch against the user's branches.
 */
class RoleMiddleware
{
    public function __construct(private readonly TokenClaimsResolver $resolver)
    {
    }

    public function handle(Request $request, Closure $next, string|array $role, ?string $guard = null): mixed
    {
        // Guests get 403, not 401, to keep the status clients already handle
        if (auth($guard ?? config('token-claims.guard'))->guest()) {
            throw ErrorResponse::unauthorizedAction();
        }

        $claims = $this->resolver->requireClaims();

        $allowedRoles = is_array($role) ? $role : explode('|', $role);
        if (!in_array($claims->role_name, $allowedRoles, true)) {
            throw ErrorResponse::unauthorizedAction();
        }

        if ($claims->check_branch &&
            !in_array('*', $claims->user_branches, true) &&
            !in_array($claims->branch_uuid, $claims->user_branches, true)
        ) {
            throw ErrorResponse::unauthorizedAction();
        }

        return $next($request);
    }
}
