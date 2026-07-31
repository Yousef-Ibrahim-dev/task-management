<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_can_be_created_for_a_user(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()->for($user)->create([
            'name' => 'Billing System Migration',
            'description' => 'Replace the legacy integration before the vendor contract ends.',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'user_id' => $user->id,
            'name' => 'Billing System Migration',
            'status' => ProjectStatus::Active->value,
        ]);
    }

    public function test_a_project_description_is_optional(): void
    {
        $project = Project::factory()->create(['description' => null]);

        $this->assertNull($project->description);
    }

    public function test_a_project_belongs_to_a_user(): void
    {
        $user = User::factory()->create();

        $project = Project::factory()->for($user)->create()->load('user');

        $this->assertTrue($project->user->is($user));
    }

    public function test_a_project_has_many_tasks(): void
    {
        $project = Project::factory()
            ->has(Task::factory()->count(3))
            ->create()
            ->load('tasks');

        $this->assertCount(3, $project->tasks);
        $this->assertInstanceOf(Task::class, $project->tasks->first());
    }

    public function test_the_status_is_cast_to_the_project_status_enum(): void
    {
        $project = Project::factory()->archived()->create();

        $this->assertSame(ProjectStatus::Archived, $project->fresh()?->status);
        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'archived',
        ]);
    }

    public function test_deleting_a_user_deletes_their_projects(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $user->delete();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_the_factory_produces_an_active_project_owned_by_a_user(): void
    {
        $project = Project::factory()->create();

        $this->assertSame(ProjectStatus::Active, $project->status);
        $this->assertNotEmpty($project->name);
        $this->assertDatabaseHas('users', ['id' => $project->user_id]);
    }
}
