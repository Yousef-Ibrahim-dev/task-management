<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Task;

use App\Interfaces\Repositories\TaskRepositoryInterface;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The API never reaches a policy denial: the repository scopes by owner, so a
 * stranger's task is a 404 long before authorization runs. These cover the
 * second layer directly.
 */
class TaskPolicyTest extends TestCase
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
        ];
    }

    private function ownedTask(User $user): Task
    {
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        // Resolved the way the controller does, so the project relation arrives
        // loaded exactly as the policy expects.
        return $this->app->make(TaskRepositoryInterface::class)
            ->findOwnedTask($task->id, $project->id, $user->id);
    }

    #[DataProvider('abilities')]
    public function test_it_allows_the_owner(string $ability): void
    {
        $user = User::factory()->create();

        $this->assertTrue((new TaskPolicy)->{$ability}($user, $this->ownedTask($user)));
    }

    #[DataProvider('abilities')]
    public function test_it_denies_a_stranger(string $ability): void
    {
        $owner = User::factory()->create();

        $this->assertFalse((new TaskPolicy)->{$ability}(User::factory()->create(), $this->ownedTask($owner)));
    }

    #[DataProvider('abilities')]
    public function test_the_gate_resolves_the_policy(string $ability): void
    {
        $user = User::factory()->create();
        $task = $this->ownedTask($user);

        $this->assertTrue($user->can($ability, $task));
        $this->assertFalse(User::factory()->create()->can($ability, $task));
    }

    #[DataProvider('abilities')]
    public function test_it_denies_rather_than_lazy_loading_when_the_project_is_absent(string $ability): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $task = Task::factory()->for($project)->create();

        // Straight from the factory the relation is unloaded. Failing closed is
        // what keeps a hidden query out of authorization.
        $this->assertFalse($task->relationLoaded('project'));
        $this->assertFalse((new TaskPolicy)->{$ability}($user, $task));
    }

    public function test_the_repository_loads_only_the_owning_columns(): void
    {
        $user = User::factory()->create();
        $task = $this->ownedTask($user);

        $this->assertTrue($task->relationLoaded('project'));
        $this->assertSame(
            ['id', 'user_id'],
            array_keys($task->project->getAttributes()),
        );
    }
}
