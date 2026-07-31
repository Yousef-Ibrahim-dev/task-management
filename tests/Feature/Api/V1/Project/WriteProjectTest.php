<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WriteProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_unauthenticated_writes(): void
    {
        $project = Project::factory()->create();

        $this->postJson('/api/v1/projects', ['name' => 'X'])->assertUnauthorized();
        $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'X'])->assertUnauthorized();
        $this->deleteJson("/api/v1/projects/{$project->id}")->assertUnauthorized();
    }

    public function test_it_creates_a_project_for_the_authenticated_user(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/projects', [
            'name' => 'Search Index Rewrite',
            'description' => 'Move search off the primary database.',
        ])
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project created successfully.')
            ->assertJsonPath('data.name', 'Search Index Rewrite')
            ->assertJsonPath('data.status', ProjectStatus::Active->value)
            ->assertJsonMissingPath('data.user_id');

        $this->assertDatabaseHas('projects', [
            'user_id' => $user->id,
            'name' => 'Search Index Rewrite',
            'status' => ProjectStatus::Active->value,
        ]);
    }

    public function test_it_accepts_a_status_from_the_enum_on_create(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/projects', [
            'name' => 'Data Warehouse Rollout',
            'status' => ProjectStatus::Completed->value,
        ])
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.status', ProjectStatus::Completed->value);
    }

    public function test_it_ignores_an_owner_supplied_in_the_request_body(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Notification Service Cleanup',
            'user_id' => $stranger->id,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertDatabaseHas('projects', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
        ]);
    }

    public function test_it_validates_the_create_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/projects', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'The given data was invalid.')
            ->assertJsonStructure(['success', 'message', 'errors' => ['name']]);

        $this->postJson('/api/v1/projects', ['name' => str_repeat('a', 256)])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['name']]);

        $this->postJson('/api/v1/projects', ['name' => 'Valid', 'status' => 'deleted'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_it_updates_a_project_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['name' => 'Old name']);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'New name',
            'description' => null,
            'status' => ProjectStatus::Completed->value,
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Project updated successfully.')
            ->assertJsonPath('data.name', 'New name')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.status', ProjectStatus::Completed->value);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'New name',
            'status' => ProjectStatus::Completed->value,
        ]);
    }

    public function test_it_validates_the_update_payload(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/projects/{$project->id}", [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_it_returns_404_when_updating_a_project_owned_by_another_user(): void
    {
        $project = Project::factory()->for(User::factory())->create(['name' => 'Untouched']);

        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/v1/projects/{$project->id}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $project->id, 'name' => 'Untouched']);
    }

    public function test_it_soft_deletes_a_project_and_returns_no_content(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/projects/{$project->id}")
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSame('', $response->getContent());
        $this->assertSoftDeleted($project);
        $this->assertDatabaseCount('projects', 1);
    }

    public function test_it_returns_404_when_deleting_a_project_owned_by_another_user(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/v1/projects/{$project->id}")->assertNotFound();

        $this->assertNotSoftDeleted($project);
    }
}
