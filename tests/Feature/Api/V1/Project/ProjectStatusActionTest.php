<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectStatusActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_unauthenticated_status_changes(): void
    {
        $project = Project::factory()->create();

        $this->patchJson("/api/v1/projects/{$project->id}/archive")->assertUnauthorized();
        $this->patchJson("/api/v1/projects/{$project->id}/restore-status")->assertUnauthorized();
    }

    public function test_it_archives_an_active_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/projects/{$project->id}/archive")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project archived successfully.')
            ->assertJsonPath('data.status', ProjectStatus::Archived->value);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::Archived->value,
        ]);
    }

    public function test_archiving_an_archived_project_is_idempotent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->archived()->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/projects/{$project->id}/archive")
            ->assertOk()
            ->assertJsonPath('data.status', ProjectStatus::Archived->value);
    }

    public function test_it_restores_an_archived_project_to_active(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->archived()->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/projects/{$project->id}/restore-status")
            ->assertOk()
            ->assertJsonPath('message', 'Project restored successfully.')
            ->assertJsonPath('data.status', ProjectStatus::Active->value);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::Active->value,
        ]);
    }

    public function test_restoring_an_active_project_is_idempotent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/projects/{$project->id}/restore-status")
            ->assertOk()
            ->assertJsonPath('data.status', ProjectStatus::Active->value);
    }

    public function test_restoring_status_does_not_bring_back_a_soft_deleted_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $project->delete();

        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/projects/{$project->id}/restore-status")->assertNotFound();

        $this->assertSoftDeleted($project);
    }

    public function test_it_returns_404_for_status_changes_on_another_users_project(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/v1/projects/{$project->id}/archive")->assertNotFound();
        $this->patchJson("/api/v1/projects/{$project->id}/restore-status")->assertNotFound();

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => ProjectStatus::Active->value,
        ]);
    }
}
