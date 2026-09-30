<?php

declare(strict_types = 1);

namespace OurEdu\TokenClaims;

/**
 * Where the IAM service lives.
 */
final class Iam
{
    public static function url(string $path): string
    {
        $base = config('token-claims.iam_url')
            ?? config('app.iam_service_url')
            ?? 'http://saas-iam-service:9000/api/v1';

        return rtrim((string) $base, '/') . '/' . ltrim($path, '/');
    }

    public static function timeout(): int
    {
        return (int) config('token-claims.timeout', 10);
    }
}
