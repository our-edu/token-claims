<?php

declare(strict_types = 1);

namespace OurEdu\TokenClaims;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fetches the current request's token claims from IAM.
 * Bound as scoped, so claims are fetched once per request (Octane safe).
 */
class TokenClaimsResolver
{
    private bool $resolved = false;

    private ?TokenClaims $claims = null;

    private ?ClaimsFailure $failure = null;

    /**
     * The claims, or null when they could not be resolved (see failure()).
     * A failed fetch is cached too, so a request never retries IAM.
     */
    public function claims(): ?TokenClaims
    {
        if (!$this->resolved) {
            $this->claims = $this->fetch();
            $this->resolved = true;
        }

        return $this->claims;
    }

    /**
     * Why claims() is null, or null when the claims resolved.
     */
    public function failure(): ?ClaimsFailure
    {
        $this->claims();

        return $this->failure;
    }

    public function hasClaims(): bool
    {
        return $this->claims() !== null;
    }

    /**
     * The claims, or stop the request: 401 when there is no token or IAM
     * refused it, 503 when IAM was unreachable or errored.
     *
     * @throws HttpResponseException
     */
    public function requireClaims(): TokenClaims
    {
        $claims = $this->claims();
        if ($claims) {
            return $claims;
        }

        throw $this->failure === ClaimsFailure::Unavailable
            ? ErrorResponse::serviceUnavailable()
            : ErrorResponse::invalidSession();
    }

    private function fetch(): ?TokenClaims
    {
        $token = request()->bearerToken();
        if (!$token) {
            $this->failure = ClaimsFailure::MissingToken;
            return null;
        }

        $url = $this->url();

        try {
            $response = Http::withToken($token)
                ->timeout((int) config('token-claims.timeout', 10))
                ->get($url);
        } catch (\Exception $e) {
            Log::error('Failed to fetch token claims from IAM service', [
                'error' => $e->getMessage(),
                'service' => config('app.name'),
                'url' => $url,
            ]);
            $this->failure = ClaimsFailure::Unavailable;
            return null;
        }

        if (!$response->successful()) {
            Log::error('IAM service returned error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'service' => config('app.name'),
                'url' => $url,
            ]);
            $this->failure = $response->serverError()
                ? ClaimsFailure::Unavailable
                : ClaimsFailure::Rejected;
            return null;
        }

        $data = $response->json('data');
        if (!is_array($data) || empty($data['user_uuid']) || empty($data['role_name'])) {
            // A 2xx without the identity claims would build a session with a null role
            Log::error('IAM service returned incomplete token claims', [
                'body' => $response->body(),
                'service' => config('app.name'),
                'url' => $url,
            ]);
            $this->failure = ClaimsFailure::Rejected;
            return null;
        }

        return new TokenClaims($data);
    }

    private function url(): string
    {
        $base = config('token-claims.iam_url')
            ?? config('app.iam_service_url')
            ?? 'http://saas-iam-service:9000/api/v1';

        return rtrim((string) $base, '/') . '/token/claims';
    }
}
