<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_it_returns_the_authenticated_user_for_a_valid_bearer_token(): void
    {
        $user = User::factory()->create([
            'name' => 'Yousef Ibrahim',
            'email' => 'yousef.ibrahim.dev@gmail.com',
        ]);

        $this->withToken($user->createToken('phone')->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'User retrieved successfully.')
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', 'Yousef Ibrahim')
            ->assertJsonPath('data.email', 'yousef.ibrahim.dev@gmail.com')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'created_at', 'updated_at']]);
    }

    public function test_it_exposes_no_credential_or_token_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->withToken($user->createToken('phone')->plainTextToken)
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        $response->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.remember_token')
            ->assertJsonMissingPath('data.email_verified_at')
            ->assertJsonMissingPath('data.tokens')
            ->assertJsonMissingPath('data.current_access_token');

        $this->assertSame(
            ['id', 'name', 'email', 'created_at', 'updated_at'],
            array_keys((array) $response->json('data')),
        );
    }

    public function test_it_rejects_a_revoked_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone');
        $plainText = $token->plainTextToken;

        $token->accessToken->delete();

        $this->withToken($plainText)->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_it_rejects_a_fabricated_token(): void
    {
        $this->withToken('1|totally-made-up-token')->getJson('/api/v1/auth/me')->assertUnauthorized();
    }
}
