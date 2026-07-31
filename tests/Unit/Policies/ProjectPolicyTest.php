<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The API never reaches a policy denial, because the repository scopes reads by
 * owner and a stranger's project already raises a 404. These cover the rules
 * directly so the second layer is verified rather than assumed.
 */
class ProjectPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function abilities(): array
    {
        return [
            'view' => ['view'],
            'update' => ['update'],
            'delete' => ['delete'],
            'archive' => ['archive'],
            'restoreStatus' => ['restoreStatus'],
        ];
    }

    #[DataProvider('abilities')]
    public function test_it_allows_the_owner(string $ability): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->assertTrue((new ProjectPolicy)->{$ability}($user, $project));
    }

    #[DataProvider('abilities')]
    public function test_it_denies_a_stranger(string $ability): void
    {
        $project = Project::factory()->for(User::factory())->create();

        $this->assertFalse((new ProjectPolicy)->{$ability}(User::factory()->create(), $project));
    }

    #[DataProvider('abilities')]
    public function test_the_gate_resolves_the_policy_for_each_ability(string $ability): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->assertTrue($user->can($ability, $project));
        $this->assertFalse(User::factory()->create()->can($ability, $project));
    }
}
