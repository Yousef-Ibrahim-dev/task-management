<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskShowTest extends TestCase
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

    public function test_it_requires_authentication(): void
    {
        $task = Task::factory()->for($this->project)->create();

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")->assertUnauthorized();
    }

    public function test_it_shows_an_owned_task(): void
    {
        $task = Task::factory()->for($this->project)->todo()->highPriority()->create([
            'title' => 'Fix the login redirect loop',
            'description' => 'Reproduced on staging.',
            'due_date' => '2026-09-15',
        ]);

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Task retrieved successfully.')
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.project_id', $this->project->id)
            ->assertJsonPath('data.title', 'Fix the login redirect loop')
            ->assertJsonPath('data.description', 'Reproduced on staging.')
            ->assertJsonPath('data.status', TaskStatus::Todo->value)
            ->assertJsonPath('data.priority', TaskPriority::High->value)
            ->assertJsonPath('data.due_date', '2026-09-15')
            ->assertJsonPath('data.completed_at', null);
    }

    public function test_it_exposes_no_deleted_at_or_ownership_data(): void
    {
        $task = Task::factory()->for($this->project)->create();

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")->assertOk();

        $response->assertJsonMissingPath('data.deleted_at')
            ->assertJsonMissingPath('data.project')
            ->assertJsonMissingPath('data.user_id');

        $this->assertSame(
            ['id', 'project_id', 'title', 'description', 'status', 'priority', 'due_date', 'completed_at', 'created_at', 'updated_at'],
            array_keys((array) $response->json('data')),
        );
    }

    public function test_it_returns_404_for_another_users_task(): void
    {
        $strangerProject = Project::factory()->for(User::factory())->create();
        $task = Task::factory()->for($strangerProject)->create();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$strangerProject->id}/tasks/{$task->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_it_returns_404_for_a_task_under_a_sibling_project(): void
    {
        $sibling = Project::factory()->for($this->user)->create();
        $task = Task::factory()->for($sibling)->create();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")->assertNotFound();
    }

    public function test_it_returns_404_for_a_missing_task(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks/999999")->assertNotFound();
    }

    public function test_it_returns_404_for_a_soft_deleted_task(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $task->delete();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")->assertNotFound();
    }

    public function test_it_returns_404_when_the_project_is_soft_deleted(): void
    {
        $task = Task::factory()->for($this->project)->create();
        $this->project->delete();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}")->assertNotFound();
    }

    public function test_a_task_inside_an_archived_project_can_still_be_viewed(): void
    {
        $archived = Project::factory()->for($this->user)->archived()->create();
        $task = Task::factory()->for($archived)->create();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$archived->id}/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);
    }
}
