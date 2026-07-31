<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskWriteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
    }

    public function test_write_endpoints_require_authentication(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $base = "/api/v1/projects/{$this->project->id}/tasks";

        $this->postJson($base, ['title' => 'X'])->assertUnauthorized();
        $this->putJson("{$base}/{$task->id}", ['title' => 'X'])->assertUnauthorized();
        $this->patchJson("{$base}/{$task->id}", ['title' => 'X'])->assertUnauthorized();
        $this->deleteJson("{$base}/{$task->id}")->assertUnauthorized();
    }

    public function test_it_creates_a_task_with_the_documented_defaults(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Fix the login redirect loop',
        ])
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Task created successfully.')
            ->assertJsonPath('data.project_id', $this->project->id)
            ->assertJsonPath('data.status', TaskStatus::Todo->value)
            ->assertJsonPath('data.priority', TaskPriority::Medium->value)
            ->assertJsonPath('data.due_date', null)
            ->assertJsonPath('data.completed_at', null);

        $this->assertDatabaseHas('tasks', [
            'project_id' => $this->project->id,
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::Medium->value,
        ]);
    }

    public function test_it_accepts_the_full_documented_payload(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Fix the login redirect loop',
            'description' => 'Reproduced on staging.',
            'status' => TaskStatus::InProgress->value,
            'priority' => TaskPriority::High->value,
            'due_date' => '2026-09-15',
        ])
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.description', 'Reproduced on staging.')
            ->assertJsonPath('data.status', TaskStatus::InProgress->value)
            ->assertJsonPath('data.priority', TaskPriority::High->value)
            ->assertJsonPath('data.due_date', '2026-09-15');
    }

    public function test_it_takes_the_project_from_the_route_and_ignores_the_body(): void
    {
        $sibling = Project::factory()->for($this->user)->create();

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Fix the login redirect loop',
            'project_id' => $sibling->id,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertSame($this->project->id, $response->json('data.project_id'));
        $this->assertDatabaseHas('tasks', [
            'id' => $response->json('data.id'),
            'project_id' => $this->project->id,
        ]);
    }

    public function test_it_ignores_a_client_supplied_completion_timestamp_on_create(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Fix the login redirect loop',
            'completed_at' => '2020-01-01 00:00:00',
        ])
            ->assertStatus(Response::HTTP_CREATED)
            ->assertJsonPath('data.completed_at', null);
    }

    public function test_it_validates_the_create_payload(): void
    {
        Sanctum::actingAs($this->user);

        $url = "/api/v1/projects/{$this->project->id}/tasks";

        $this->postJson($url, [])->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['title']]);

        $this->postJson($url, ['title' => str_repeat('a', 256)])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['title']]);

        $this->postJson($url, ['title' => 'Valid', 'status' => 'archived'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['status']]);

        $this->postJson($url, ['title' => 'Valid', 'priority' => 'urgent'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['priority']]);

        $this->postJson($url, ['title' => 'Valid', 'due_date' => 'not-a-date'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['due_date']]);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_it_returns_404_when_creating_inside_another_users_project(): void
    {
        $strangerProject = Project::factory()->for(User::factory())->create();

        Sanctum::actingAs($this->user);

        $this->postJson("/api/v1/projects/{$strangerProject->id}/tasks", ['title' => 'Injected'])
            ->assertNotFound();

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_it_updates_a_task(): void
    {
        $task = Task::factory()->for($this->project)->todo()->lowPriority()->create(['title' => 'Old title']);

        Sanctum::actingAs($this->user);

        $this->putJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'title' => 'New title',
            'description' => null,
            'priority' => TaskPriority::High->value,
            'due_date' => '2026-10-01',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Task updated successfully.')
            ->assertJsonPath('data.title', 'New title')
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.priority', TaskPriority::High->value)
            ->assertJsonPath('data.due_date', '2026-10-01');
    }

    public function test_it_allows_a_partial_patch(): void
    {
        $task = Task::factory()->for($this->project)->todo()->highPriority()->create([
            'title' => 'Original title',
            'description' => 'Original description',
        ]);

        Sanctum::actingAs($this->user);

        $this->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'title' => 'Renamed',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.description', 'Original description')
            ->assertJsonPath('data.priority', TaskPriority::High->value);
    }

    public function test_it_keeps_the_task_in_its_current_project(): void
    {
        $sibling = Project::factory()->for($this->user)->create();
        $task = Task::factory()->for($this->project)->create();

        Sanctum::actingAs($this->user);

        $this->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'project_id' => $sibling->id,
        ])->assertOk()->assertJsonPath('data.project_id', $this->project->id);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => $this->project->id]);
    }

    public function test_it_validates_the_update_payload(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $url = "/api/v1/projects/{$this->project->id}/tasks/{$task->id}";

        Sanctum::actingAs($this->user);

        $this->patchJson($url, ['status' => 'archived'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['status']]);

        $this->patchJson($url, ['priority' => 'urgent'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['priority']]);

        $this->patchJson($url, ['due_date' => 'not-a-date'])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['due_date']]);

        $this->patchJson($url, ['title' => ''])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['title']]);
    }

    public function test_it_returns_404_when_updating_a_task_it_does_not_own(): void
    {
        $strangerProject = Project::factory()->for(User::factory())->create();
        $strangerTask = Task::factory()->for($strangerProject)->create(['title' => 'Untouched']);

        $sibling = Project::factory()->for($this->user)->create();
        $siblingTask = Task::factory()->for($sibling)->create(['title' => 'Also untouched']);

        Sanctum::actingAs($this->user);

        $this->putJson("/api/v1/projects/{$strangerProject->id}/tasks/{$strangerTask->id}", ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->putJson("/api/v1/projects/{$this->project->id}/tasks/{$siblingTask->id}", ['title' => 'Hijacked'])
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $strangerTask->id, 'title' => 'Untouched']);
        $this->assertDatabaseHas('tasks', ['id' => $siblingTask->id, 'title' => 'Also untouched']);
    }

    public function test_it_returns_404_when_updating_a_soft_deleted_task(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $task->delete();

        Sanctum::actingAs($this->user);

        $this->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", ['title' => 'Revived'])
            ->assertNotFound();
    }

    public function test_it_soft_deletes_a_task_and_returns_an_empty_204(): void
    {
        $task = Task::factory()->for($this->project)->create();

        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSame('', $response->getContent());
        $this->assertSoftDeleted($task);
        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('projects', ['id' => $this->project->id]);
    }

    public function test_it_returns_404_when_deleting_a_task_it_does_not_own(): void
    {
        $strangerProject = Project::factory()->for(User::factory())->create();
        $strangerTask = Task::factory()->for($strangerProject)->create();

        $sibling = Project::factory()->for($this->user)->create();
        $siblingTask = Task::factory()->for($sibling)->create();

        Sanctum::actingAs($this->user);

        $this->deleteJson("/api/v1/projects/{$strangerProject->id}/tasks/{$strangerTask->id}")->assertNotFound();
        $this->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$siblingTask->id}")->assertNotFound();

        $this->assertNotSoftDeleted($strangerTask);
        $this->assertNotSoftDeleted($siblingTask);
    }
}
