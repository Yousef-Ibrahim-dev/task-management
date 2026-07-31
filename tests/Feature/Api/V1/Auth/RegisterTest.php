<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Yousef Ibrahim',
            'email' => 'yousef.ibrahim.dev@gmail.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ], $overrides);
    }

    public function test_it_registers_a_user_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload());

        $response->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Registered successfully.')
            ->assertJsonPath('data.user.name', 'Yousef Ibrahim')
            ->assertJsonPath('data.user.email', 'yousef.ibrahim.dev@gmail.com')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 86_400)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'created_at', 'updated_at'],
                    'token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        $this->assertIsString($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_it_exposes_no_credential_fields_on_the_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload());

        $response->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token')
            ->assertJsonMissingPath('data.user.email_verified_at')
            ->assertJsonMissingPath('data.user.tokens');
    }

    public function test_it_persists_the_user_with_a_hashed_password(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertStatus(Response::HTTP_CREATED);

        $user = User::query()->where('email', 'yousef.ibrahim.dev@gmail.com')->firstOrFail();

        $this->assertNotSame('secret-password', $user->password);
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_it_normalises_the_email_before_persisting(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'email' => '  Yousef.Ibrahim.DEV@Gmail.COM  ',
        ]))->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.user.email', 'yousef.ibrahim.dev@gmail.com');

        $this->assertDatabaseHas('users', ['email' => 'yousef.ibrahim.dev@gmail.com']);
    }

    public function test_it_persists_the_issued_token(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'device_name' => 'iphone-15',
        ]))->assertStatus(Response::HTTP_CREATED);

        $user = User::query()->where('email', 'yousef.ibrahim.dev@gmail.com')->firstOrFail();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'iphone-15',
        ]);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_it_ignores_administrative_fields_in_the_payload(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'id' => 999,
            'email_verified_at' => '2020-01-01 00:00:00',
            'remember_token' => 'injected',
        ]))->assertStatus(Response::HTTP_CREATED);

        $user = User::query()->where('email', 'yousef.ibrahim.dev@gmail.com')->firstOrFail();

        $this->assertNotSame(999, $user->id);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->remember_token);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'name is required' => [['name' => ''], 'name'],
            'email is required' => [['email' => ''], 'email'],
            'email must be valid' => [['email' => 'not-an-email'], 'email'],
            'password is required' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'password must be confirmed' => [['password_confirmation' => 'different'], 'password'],
            'password must be long enough' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidPayloads')]
    public function test_it_validates_the_payload(array $overrides, string $field): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload($overrides))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors' => [$field]]);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_rejects_a_duplicate_email_regardless_of_casing(): void
    {
        User::factory()->create(['email' => 'yousef.ibrahim.dev@gmail.com']);

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'YOUSEF.IBRAHIM.DEV@gmail.com']))
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['email']]);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_it_throttles_repeated_registration_attempts(): void
    {
        config(['api.rate_limit.register_per_minute' => 2]);

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'one@example.com']))
            ->assertStatus(Response::HTTP_CREATED);
        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'two@example.com']))
            ->assertStatus(Response::HTTP_CREATED);

        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'three@example.com']))
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Too many requests. Please retry later.');
    }
}
