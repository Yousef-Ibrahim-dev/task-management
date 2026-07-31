<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Every case here drives a real personal access token: Sanctum::actingAs()
 * installs a transient token, which would make revocation untestable.
 */
class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_authentication(): void
    {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_it_returns_no_content_with_an_empty_body(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $response->getContent());
    }

    public function test_it_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('phone')->plainTextToken;
        $laptop = $user->createToken('laptop')->plainTextToken;

        $this->assertSame(2, $user->tokens()->count());

        $this->withToken($phone)->postJson('/api/v1/auth/logout')
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'laptop',
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'phone',
        ]);

        // The other device is untouched and still usable.
        $this->forgetResolvedGuard();
        $this->withToken($laptop)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_the_revoked_token_can_no_longer_be_used(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('phone')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->forgetResolvedGuard();
        $this->withToken($token)->getJson('/api/v1/auth/me')->assertUnauthorized();

        $this->forgetResolvedGuard();
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function test_it_does_not_touch_another_users_tokens(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        $token = $user->createToken('phone')->plainTextToken;
        $stranger->createToken('phone');

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(1, $stranger->tokens()->count());
    }


    private function forgetResolvedGuard(): void
    {
        Auth::forgetGuards();
    }
}
