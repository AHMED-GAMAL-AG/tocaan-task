<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate the next requests with a real JWT for the user, so the
     * whole auth:api middleware stack is exercised (not actingAs()).
     */
    protected function actingAsJwt(?User $user = null): static
    {
        $user ??= User::factory()->create();

        return $this->withToken(JWTAuth::fromUser($user));
    }

    /**
     * The test app is shared between requests in one test, and the JWT
     * services cache the last parsed token. Reset them so the next request
     * authenticates from its own header, as a real request would.
     */
    protected function forgetAuthState(): static
    {
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('tymon.jwt');
        $this->app->forgetInstance('tymon.jwt.auth');
        JWTAuth::clearResolvedInstance('tymon.jwt.auth');

        return $this;
    }
}
