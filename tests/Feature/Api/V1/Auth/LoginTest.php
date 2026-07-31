<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password';

    private const INVALID_MESSAGE = 'The provided credentials are incorrect.';

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'yousef.ibrahim.dev@gmail.com',
            'password' => self::PASSWORD,
        ]);
    }

    public function test_it_logs_in_and_returns_a_token(): void
    {
        $user = $this->user();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'yousef.ibrahim.dev@gmail.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Logged in successfully.')
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.email', 'yousef.ibrahim.dev@gmail.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 86_400)
            ->assertJsonMissingPath('data.user.password');

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_it_names_the_token_after_the_device(): void
    {
        $user = $this->user();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'yousef.ibrahim.dev@gmail.com',
            'password' => self::PASSWORD,
            'device_name' => 'macbook',
        ])->assertOk();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'macbook',
        ]);
    }

    public function test_it_accepts_a_differently_cased_email(): void
    {
        $this->user();

        $this->postJson('/api/v1/auth/login', [
            'email' => '  YOUSEF.IBRAHIM.DEV@Gmail.com ',
            'password' => self::PASSWORD,
        ])->assertOk();
    }

    public function test_it_returns_the_same_failure_for_an_unknown_email_and_a_wrong_password(): void
    {
        $this->user();

        $unknownEmail = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => self::PASSWORD,
        ]);

        $wrongPassword = $this->postJson('/api/v1/auth/login', [
            'email' => 'yousef.ibrahim.dev@gmail.com',
            'password' => 'not-the-password',
        ]);

        $expected = [
            'success' => false,
            'message' => self::INVALID_MESSAGE,
            'errors' => ['email' => [self::INVALID_MESSAGE]],
        ];

        $unknownEmail->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)->assertExactJson($expected);
        $wrongPassword->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)->assertExactJson($expected);

        // Identical bodies and statuses: nothing distinguishes a registered
        // address from an unregistered one.
        $this->assertSame($unknownEmail->json(), $wrongPassword->json());
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_validates_the_payload(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['email', 'password']]);

        $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email', 'password' => 'x'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_each_login_issues_a_separate_token(): void
    {
        $user = $this->user();

        $first = $this->postJson('/api/v1/auth/login', [
            'email' => 'yousef.ibrahim.dev@gmail.com',
            'password' => self::PASSWORD,
        ])->assertOk();

        $second = $this->postJson('/api/v1/auth/login', [
            'email' => 'yousef.ibrahim.dev@gmail.com',
            'password' => self::PASSWORD,
        ])->assertOk();

        $this->assertNotSame($first->json('data.token'), $second->json('data.token'));
        $this->assertSame(2, $user->tokens()->count());
    }

    public function test_it_throttles_repeated_login_attempts(): void
    {
        config(['api.rate_limit.login_per_minute' => 2]);

        $this->user();

        $attempt = fn (): TestResponse => $this->postJson('/api/v1/auth/login', [
            'email' => 'yousef.ibrahim.dev@gmail.com',
            'password' => 'wrong',
        ]);

        $attempt()->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $attempt()->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $attempt()->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests. Please retry later.');
    }

    public function test_the_login_limiter_is_scoped_per_email(): void
    {
        config(['api.rate_limit.login_per_minute' => 1]);

        $this->user();

        $this->postJson('/api/v1/auth/login', ['email' => 'yousef.ibrahim.dev@gmail.com', 'password' => 'wrong'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->postJson('/api/v1/auth/login', ['email' => 'yousef.ibrahim.dev@gmail.com', 'password' => 'wrong'])
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS);

        // A different account from the same address still has its own budget.
        $this->postJson('/api/v1/auth/login', ['email' => 'someone.ibrahim.dev@gmail.com', 'password' => 'wrong'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
