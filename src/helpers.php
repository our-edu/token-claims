<?php

declare(strict_types = 1);

use OurEdu\TokenClaims\PermissionAuthorizer;
use OurEdu\TokenClaims\TokenClaimsResolver;

if (!function_exists('token_claims')) {
    function token_claims(): TokenClaimsResolver
    {
        return app(TokenClaimsResolver::class);
    }
}

if (!function_exists('iam_can')) {
    /**
     * Whether IAM grants the current token this permission; 503 if IAM is down.
     */
    function iam_can(string $resource, string $action): bool
    {
        return app(PermissionAuthorizer::class)->allows($resource, $action);
    }
}
