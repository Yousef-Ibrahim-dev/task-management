<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private UserRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(UserRepositoryInterface::class);
    }

    public function test_the_container_resolves_the_bound_implementation(): void
    {
        $this->assertInstanceOf(UserRepository::class, $this->repository);
    }

    public function test_it_finds_a_user_by_email(): void
    {
        $user = User::factory()->create(['email' => 'yousef@example.com']);

        $this->assertTrue($this->repository->findByEmail('yousef@example.com')?->is($user));
    }

    public function test_it_returns_null_when_no_user_matches(): void
    {
        User::factory()->create(['email' => 'yousef@example.com']);

        $this->assertNull($this->repository->findByEmail('nobody@example.com'));
    }

    public function test_the_lookup_leaves_casing_to_the_column_collation(): void
    {
        $user = User::factory()->create(['email' => 'yousef@example.com']);

        // utf8mb4_unicode_ci is case insensitive, so this matches. The service
        // still normalises before writing, which is what keeps stored addresses
        // canonical and would keep the lookup correct under a binary collation.
        $this->assertTrue($this->repository->findByEmail('YOUSEF@example.com')?->is($user));
    }

    public function test_it_creates_a_user(): void
    {
        $user = $this->repository->create([
            'name' => 'Yousef Ibrahim',
            'email' => 'yousef@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Yousef Ibrahim',
            'email' => 'yousef@example.com',
        ]);
    }

    public function test_it_persists_a_password_that_was_already_hashed_without_hashing_it_again(): void
    {
        $hash = Hash::make('secret-password');

        $user = $this->repository->create([
            'name' => 'Yousef Ibrahim',
            'email' => 'yousef@example.com',
            'password' => $hash,
        ]);

        $this->assertSame($hash, $user->fresh()?->password);
        $this->assertTrue(Hash::check('secret-password', (string) $user->fresh()?->password));
    }

    public function test_it_refuses_fields_outside_the_contract(): void
    {
        // Strict models reject unfillable keys loudly rather than dropping them,
        // so an injected field can never reach the row unnoticed.
        $this->expectException(MassAssignmentException::class);

        /** @phpstan-ignore argument.type (deliberately passing keys the contract excludes) */
        $this->repository->create([
            'name' => 'Yousef Ibrahim',
            'email' => 'yousef@example.com',
            'password' => Hash::make('secret-password'),
            'remember_token' => 'injected',
        ]);
    }
}
