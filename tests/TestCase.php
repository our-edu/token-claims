<?php

declare(strict_types = 1);

namespace OurEdu\TokenClaims\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Orchestra\Testbench\TestCase as Orchestra;
use OurEdu\TokenClaims\TokenClaimsServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    protected function getPackageProviders($app): array
    {
        return [TokenClaimsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('token-claims.iam_url', 'http://iam.test/api/v1/');
    }

    /**
     * Start a fresh request, so scoped instances (the resolver) are rebuilt.
     */
    protected function withBearer(?string $token): void
    {
        $request = Request::create('/anything');
        if ($token) {
            $request->headers->set('Authorization', 'Bearer ' . $token);
        }
        $this->app->instance('request', $request);
        $this->app->forgetScopedInstances();
    }

    protected function validClaims(array $overrides = []): array
    {
        return [
            'data' => array_merge([
                'user_uuid' => 'user-1',
                'role_uuid' => 'role-1',
                'role_name' => 'student',
                'branch' => '*',
                'user_branches' => ['*'],
                'is_valid' => true,
                'tenant_id' => 1,
            ], $overrides),
        ];
    }
}
