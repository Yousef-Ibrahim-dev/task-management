<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\InvalidCredentialsException;
use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * The repository is mocked; users and tokens are real, because Sanctum token
 * creation is an Eloquent concern that a mock could only pretend to verify.
 */
class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'secret-password';

    private UserRepositoryInterface&MockInterface $users;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->users = Mockery::mock(UserRepositoryInterface::class);
        $this->service = new AuthService($this->users, $this->app->make(Hasher::class));
    }

    public function test_it_hashes_the_password_and_normalises_the_email_before_persisting(): void
    {
        $user = User::factory()->create();
        $captured = [];

        $this->users->shouldReceive('create')
            ->once()
            ->andReturnUsing(
                /** @param array<string, mixed> $data */
                function (array $data) use (&$captured, $user): User {
                    $captured = $data;

                    return $user;
                }
            );

        $this->service->register([
            'name' => 'Yousef Ibrahim',
            'email' => '  Yousef@Example.COM ',
            'password' => self::PASSWORD,
        ], 'phone');

        $this->assertSame('yousef@example.com', $captured['email']);
        $this->assertSame('Yousef Ibrahim', $captured['name']);
        $this->assertNotSame(self::PASSWORD, $captured['password']);
        $this->assertTrue(Hash::check(self::PASSWORD, (string) $captured['password']));
    }

    public function test_register_returns_the_user_and_a_plain_text_token(): void
    {
        $user = User::factory()->create();

        $this->users->shouldReceive('create')->once()->andReturn($user);

        $result = $this->service->register([
            'name' => 'Yousef Ibrahim',
            'email' => 'yousef@example.com',
            'password' => self::PASSWORD,
        ], 'phone');

        $this->assertTrue($result->user->is($user));
        $this->assertNotEmpty($result->token);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'phone',
        ]);
    }

    public function test_it_logs_in_with_valid_credentials(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->users->shouldReceive('findByEmail')
            ->once()
            ->with('yousef@example.com')
            ->andReturn($user);

        $result = $this->service->login('  YOUSEF@Example.com ', self::PASSWORD, 'laptop');

        $this->assertTrue($result->user->is($user));
        $this->assertNotEmpty($result->token);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'laptop',
        ]);
    }

    public function test_it_rejects_an_unknown_account(): void
    {
        $this->users->shouldReceive('findByEmail')->once()->andReturn(null);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect.');

        $this->service->login('nobody@example.com', self::PASSWORD, 'phone');
    }

    public function test_it_rejects_a_wrong_password(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->users->shouldReceive('findByEmail')->once()->andReturn($user);

        $this->expectException(InvalidCredentialsException::class);
        $this->expectExceptionMessage('The provided credentials are incorrect.');

        $this->service->login('yousef@example.com', 'not-the-password', 'phone');
    }

    public function test_a_failed_login_issues_no_token(): void
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->users->shouldReceive('findByEmail')->twice()->andReturn(null, $user);

        foreach ([['nobody@example.com', self::PASSWORD], ['yousef@example.com', 'wrong']] as [$email, $password]) {
            try {
                $this->service->login($email, $password, 'phone');
                $this->fail('Invalid credentials should not authenticate.');
            } catch (InvalidCredentialsException) {
                // expected
            }
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_it_revokes_only_the_supplied_token(): void
    {
        $user = User::factory()->create();
        $phone = $user->createToken('phone')->accessToken;
        $user->createToken('laptop');

        $this->service->logout($phone);

        $this->assertSame(1, $user->tokens()->count());
        $this->assertDatabaseMissing('personal_access_tokens', ['name' => 'phone']);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'laptop']);
    }
}
