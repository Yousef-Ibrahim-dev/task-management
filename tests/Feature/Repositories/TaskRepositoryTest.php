<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Interfaces\Repositories\TaskRepositoryInterface;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private TaskRepositoryInterface $repository;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->app->make(TaskRepositoryInterface::class);
        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
    }

    public function test_it_lists_only_the_tasks_of_the_given_project(): void
    {
        Task::factory()->count(3)->for($this->project)->create();

        $page = $this->repository->paginateForProject($this->project->id, $this->user->id);

        $this->assertSame(3, $page->total());
    }

    public function test_it_excludes_tasks_from_another_project_of_the_same_user(): void
    {
        Task::factory()->count(2)->for($this->project)->create();
        Task::factory()->count(4)->for(Project::factory()->for($this->user))->create();

        $page = $this->repository->paginateForProject($this->project->id, $this->user->id);

        $this->assertSame(2, $page->total());
    }

    public function test_it_excludes_tasks_from_another_users_project(): void
    {
        $stranger = User::factory()->create();
        $strangerProject = Project::factory()->for($stranger)->create();
        Task::factory()->count(3)->for($strangerProject)->create();

        $page = $this->repository->paginateForProject($strangerProject->id, $this->user->id);

        $this->assertSame(0, $page->total());
    }

    public function test_it_excludes_tasks_under_a_soft_deleted_project(): void
    {
        Task::factory()->count(3)->for($this->project)->create();

        $this->project->delete();

        $this->assertSame(0, $this->repository->paginateForProject($this->project->id, $this->user->id)->total());
    }

    public function test_it_excludes_soft_deleted_tasks(): void
    {
        Task::factory()->count(2)->for($this->project)->create();
        Task::factory()->for($this->project)->create()->delete();

        $this->assertSame(2, $this->repository->paginateForProject($this->project->id, $this->user->id)->total());
    }

    public function test_it_filters_by_status(): void
    {
        Task::factory()->count(2)->for($this->project)->todo()->create();
        Task::factory()->count(3)->for($this->project)->done()->create();

        $page = $this->repository->paginateForProject($this->project->id, $this->user->id, null, TaskStatus::Done);

        $this->assertSame(3, $page->total());
    }

    public function test_it_filters_by_priority(): void
    {
        Task::factory()->count(2)->for($this->project)->highPriority()->create();
        Task::factory()->count(5)->for($this->project)->lowPriority()->create();

        $page = $this->repository->paginateForProject($this->project->id, $this->user->id, null, null, TaskPriority::High);

        $this->assertSame(2, $page->total());
    }

    public function test_it_searches_by_title(): void
    {
        Task::factory()->for($this->project)->create(['title' => 'Fix the login redirect loop']);
        Task::factory()->for($this->project)->create(['title' => 'Document the export format']);

        $page = $this->repository->paginateForProject($this->project->id, $this->user->id, null, null, null, 'login');

        $this->assertSame(1, $page->total());
    }

    public function test_it_does_not_search_descriptions(): void
    {
        Task::factory()->for($this->project)->create([
            'title' => 'Document the export format',
            'description' => 'The login screen is unrelated.',
        ]);

        $page = $this->repository->paginateForProject($this->project->id, $this->user->id, null, null, null, 'login');

        $this->assertSame(0, $page->total());
    }

    public function test_it_trims_the_search_term(): void
    {
        Task::factory()->for($this->project)->create(['title' => 'Fix the login redirect loop']);

        $page = $this->repository->paginateForProject($this->project->id, $this->user->id, null, null, null, '   login   ');

        $this->assertSame(1, $page->total());
    }

    public function test_it_ignores_a_blank_search_term(): void
    {
        Task::factory()->count(3)->for($this->project)->create();

        $this->assertSame(3, $this->repository->paginateForProject($this->project->id, $this->user->id, null, null, null, '   ')->total());
        $this->assertSame(3, $this->repository->paginateForProject($this->project->id, $this->user->id, null, null, null, '')->total());
    }

    public function test_it_treats_search_wildcards_as_literal_characters(): void
    {
        Task::factory()->for($this->project)->create(['title' => 'Reduce bounce rate by 50% overall']);
        Task::factory()->for($this->project)->create(['title' => 'Document the export format']);

        $page = $this->repository->paginateForProject($this->project->id, $this->user->id, null, null, null, '50%');

        $this->assertSame(1, $page->total());
    }

    public function test_it_combines_status_priority_and_search(): void
    {
        Task::factory()->for($this->project)->todo()->highPriority()->create(['title' => 'Fix the login redirect loop']);
        Task::factory()->for($this->project)->done()->highPriority()->create(['title' => 'Fix the login banner']);
        Task::factory()->for($this->project)->todo()->lowPriority()->create(['title' => 'Fix the login copy']);
        Task::factory()->for($this->project)->todo()->highPriority()->create(['title' => 'Document the export format']);

        $page = $this->repository->paginateForProject(
            $this->project->id,
            $this->user->id,
            null,
            TaskStatus::Todo,
            TaskPriority::High,
            'login',
        );

        $this->assertSame(1, $page->total());
    }

    public function test_it_falls_back_to_the_configured_default_page_size(): void
    {
        config(['api.pagination.default' => 5]);

        Task::factory()->count(8)->for($this->project)->create();

        $page = $this->repository->paginateForProject($this->project->id, $this->user->id);

        $this->assertSame(5, $page->perPage());
        $this->assertSame(8, $page->total());
    }

    public function test_it_clamps_an_oversized_page_size(): void
    {
        config(['api.pagination.max' => 10]);

        Task::factory()->count(3)->for($this->project)->create();

        $this->assertSame(10, $this->repository->paginateForProject($this->project->id, $this->user->id, 5_000)->perPage());
    }

    public function test_it_finds_a_task_inside_the_owned_project(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $found = $this->repository->findOwnedTask($task->id, $this->project->id, $this->user->id);

        $this->assertTrue($found->is($task));
    }

    public function test_it_does_not_find_a_task_through_another_project_of_the_same_user(): void
    {
        $other = Project::factory()->for($this->user)->create();
        $task = Task::factory()->for($other)->create();

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOwnedTask($task->id, $this->project->id, $this->user->id);
    }

    public function test_it_does_not_find_another_users_task(): void
    {
        $strangerProject = Project::factory()->for(User::factory())->create();
        $task = Task::factory()->for($strangerProject)->create();

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOwnedTask($task->id, $strangerProject->id, $this->user->id);
    }

    public function test_it_does_not_find_a_task_under_a_soft_deleted_project(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $this->project->delete();

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOwnedTask($task->id, $this->project->id, $this->user->id);
    }

    public function test_it_does_not_find_a_task_that_does_not_exist(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOwnedTask(999_999, $this->project->id, $this->user->id);
    }

    public function test_it_creates_a_task_inside_the_given_project(): void
    {
        $task = $this->repository->createForProject($this->project->id, $this->user->id, [
            'title' => 'Fix the login redirect loop',
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => $this->project->id,
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
        ]);
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertSame(TaskPriority::Medium, $task->priority);
    }

    public function test_it_ignores_a_project_supplied_in_the_payload(): void
    {
        $other = Project::factory()->for($this->user)->create();

        /** @phpstan-ignore argument.type (deliberately passing an unexpected key) */
        $task = $this->repository->createForProject($this->project->id, $this->user->id, [
            'title' => 'Fix the login redirect loop',
            'project_id' => $other->id,
        ]);

        $this->assertSame($this->project->id, $task->project_id);
    }

    public function test_it_refuses_to_create_a_task_inside_another_users_project(): void
    {
        $strangerProject = Project::factory()->for(User::factory())->create();

        $this->expectException(ModelNotFoundException::class);

        $this->repository->createForProject($strangerProject->id, $this->user->id, ['title' => 'Injected']);
    }

    public function test_it_refuses_to_create_a_task_inside_a_soft_deleted_project(): void
    {
        $this->project->delete();

        $this->expectException(ModelNotFoundException::class);

        $this->repository->createForProject($this->project->id, $this->user->id, ['title' => 'Orphan']);
    }

    public function test_it_updates_a_task(): void
    {
        $task = Task::factory()->for($this->project)->todo()->create(['title' => 'Old title']);

        $updated = $this->repository->update($task, [
            'title' => 'New title',
            'priority' => TaskPriority::High,
        ]);

        $this->assertSame('New title', $updated->title);
        $this->assertSame(TaskPriority::High, $updated->priority);
        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'New title',
            'priority' => TaskPriority::High->value,
        ]);
    }

    public function test_it_soft_deletes_a_task(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->repository->delete($task);

        $this->assertSoftDeleted($task);
        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_a_soft_deleted_task_is_no_longer_found(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->repository->delete($task);

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOwnedTask($task->id, $this->project->id, $this->user->id);
    }
}
