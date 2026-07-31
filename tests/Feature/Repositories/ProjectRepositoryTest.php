<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Enums\ProjectStatus;
use App\Interfaces\Repositories\ProjectRepositoryInterface;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProjectRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolved from the container so the binding is exercised too.
        $this->repository = $this->app->make(ProjectRepositoryInterface::class);
    }

    public function test_it_paginates_only_the_projects_owned_by_the_given_user(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(3)->for($user)->create();

        $page = $this->repository->paginateForUser($user->id);

        $this->assertSame(3, $page->total());
        $this->assertCount(3, $page->items());
    }

    public function test_it_excludes_projects_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        Project::factory()->count(2)->for($user)->create();
        Project::factory()->count(5)->for($stranger)->create();

        $page = $this->repository->paginateForUser($user->id);

        $this->assertSame(2, $page->total());

        foreach ($page->items() as $project) {
            $this->assertSame($user->id, $project->user_id);
        }
    }

    public function test_it_filters_the_listing_by_status(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->for($user)->create();
        Project::factory()->count(3)->for($user)->archived()->create();
        Project::factory()->count(4)->for($user)->completed()->create();

        $archived = $this->repository->paginateForUser($user->id, null, ProjectStatus::Archived);
        $active = $this->repository->paginateForUser($user->id, null, ProjectStatus::Active);
        $completed = $this->repository->paginateForUser($user->id, null, ProjectStatus::Completed);

        $this->assertSame(3, $archived->total());
        $this->assertSame(2, $active->total());
        $this->assertSame(4, $completed->total());
        $this->assertSame(9, $this->repository->paginateForUser($user->id)->total());
    }

    public function test_it_falls_back_to_the_configured_default_page_size(): void
    {
        config(['api.pagination.default' => 5]);

        $user = User::factory()->create();
        Project::factory()->count(8)->for($user)->create();

        $page = $this->repository->paginateForUser($user->id);

        $this->assertSame(5, $page->perPage());
        $this->assertCount(5, $page->items());
        $this->assertSame(8, $page->total());
    }

    public function test_it_clamps_an_oversized_page_size_to_the_configured_maximum(): void
    {
        config(['api.pagination.max' => 10]);

        $user = User::factory()->create();
        Project::factory()->count(3)->for($user)->create();

        $page = $this->repository->paginateForUser($user->id, 5_000);

        $this->assertSame(10, $page->perPage());
    }

    public function test_it_clamps_a_non_positive_page_size(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create();

        $this->assertSame(1, $this->repository->paginateForUser($user->id, 0)->perPage());
    }

    public function test_it_finds_a_project_owned_by_the_given_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $found = $this->repository->findOwnedByUser($project->id, $user->id);

        $this->assertTrue($found->is($project));
    }

    public function test_it_throws_when_the_project_belongs_to_another_user(): void
    {
        $stranger = User::factory()->create();
        $project = Project::factory()->for($stranger)->create();

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOwnedByUser($project->id, User::factory()->create()->id);
    }

    public function test_it_throws_when_the_project_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOwnedByUser(404, $user->id);
    }

    public function test_it_creates_a_project_owned_by_the_given_user(): void
    {
        $user = User::factory()->create();

        $project = $this->repository->createForUser($user->id, [
            'name' => 'Billing System Migration',
            'description' => 'Replace the legacy integration.',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'user_id' => $user->id,
            'name' => 'Billing System Migration',
            'status' => ProjectStatus::Active->value,
        ]);
    }

    public function test_it_ignores_an_owner_supplied_in_the_payload(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();

        /** @phpstan-ignore argument.type (deliberately passing an unexpected key) */
        $project = $this->repository->createForUser($owner->id, [
            'name' => 'Search Index Rewrite',
            'user_id' => $stranger->id,
        ]);

        $this->assertSame($owner->id, $project->user_id);
    }

    public function test_it_updates_a_project(): void
    {
        $project = Project::factory()->create(['name' => 'Old name']);

        $updated = $this->repository->update($project, [
            'name' => 'New name',
            'status' => ProjectStatus::Archived,
        ]);

        $this->assertSame('New name', $updated->name);
        $this->assertSame(ProjectStatus::Archived, $updated->status);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'New name',
            'status' => ProjectStatus::Archived->value,
        ]);
    }

    public function test_it_soft_deletes_a_project(): void
    {
        $project = Project::factory()->create();

        $this->repository->delete($project);

        $this->assertSoftDeleted($project);
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_it_treats_a_soft_deleted_project_as_missing(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $this->repository->delete($project);

        $this->expectException(ModelNotFoundException::class);

        $this->repository->findOwnedByUser($project->id, $user->id);
    }

    public function test_it_excludes_soft_deleted_projects_from_the_listing(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->for($user)->create();
        $removed = Project::factory()->for($user)->create();

        $this->repository->delete($removed);

        $this->assertSame(2, $this->repository->paginateForUser($user->id)->total());
    }

    public function test_it_keeps_the_tasks_of_a_soft_deleted_project(): void
    {
        $project = Project::factory()->has(Task::factory()->count(2))->create();

        $this->repository->delete($project);

        $this->assertDatabaseCount('tasks', 2);
        $this->assertSame(2, Task::query()->count());
    }
}
