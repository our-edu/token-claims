<?php

declare(strict_types = 1);

namespace OurEdu\TokenClaims;

use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Builds the JSON error body every service already returns.
 */
final class ErrorResponse
{
    public static function invalidSession(): HttpResponseException
    {
        return self::make(401, 'invalid_session');
    }

    public static function serviceUnavailable(): HttpResponseException
    {
        return self::make(503, 'session_service_unavailable');
    }

    public static function unauthorizedAction(): HttpResponseException
    {
        return self::make(403, 'unauthorized_action');
    }

    private static function make(int $status, string $title): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'errors' => [
                [
                    'status' => $status,
                    'title' => $title,
                    'detail' => trans(config("token-claims.messages.$title", $title)),
                ],
            ],
        ], $status));
    }
}
