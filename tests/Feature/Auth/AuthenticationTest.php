<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in', 'user' => ['id', 'name', 'email']]])
            ->assertJsonPath('data.token_type', 'bearer')
            ->assertJsonPath('data.user.email', 'jane@example.com')
            ->assertJsonMissingPath('data.user.password');

        $this->assertTrue(Hash::check('secret-password', User::firstWhere('email', 'jane@example.com')->password));
    }

    public function test_registration_validates_input(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_rejects_an_existing_email(): void
    {
        User::factory()->create(['email' => 'jane@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'An account with this email address already exists.');
    }

    public function test_user_can_log_in(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'secret-password'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'expires_in', 'user']])
            ->assertJsonPath('data.expires_in', 3600);
    }

    public function test_login_with_wrong_password_is_rejected(): void
    {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'secret-password']);

        $this->postJson('/api/v1/auth/login', ['email' => 'jane@example.com', 'password' => 'wrong-password'])
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'The provided credentials are incorrect.',
                'error' => 'invalid_credentials',
            ]);
    }

    public function test_login_validates_input(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_returns_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
        $this->getJson('/api/v1/orders')->assertUnauthorized();
        $this->getJson('/api/v1/payments')->assertUnauthorized();
    }

    public function test_a_garbage_token_is_rejected(): void
    {
        $this->withToken('not.a.jwt')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_logout_invalidates_the_token(): void
    {
        $token = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->json('data.access_token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->forgetAuthState()->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_token_can_be_refreshed_and_the_old_one_is_revoked(): void
    {
        $user = User::factory()->create();
        $old = auth('api')->login($user);
        $this->forgetAuthState();

        $new = $this->withToken($old)->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->json('data.access_token');

        $this->assertNotSame($old, $new);

        $this->forgetAuthState()->withToken($new)->getJson('/api/v1/auth/me')->assertOk();

        $this->forgetAuthState()->withToken($old)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_refresh_without_a_token_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'invalid_token');
    }
}
