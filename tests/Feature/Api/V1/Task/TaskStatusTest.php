<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Task;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskStatusTest extends TestCase
{
    use RefreshDatabase;

    private const READ_ONLY_MESSAGE = 'An archived project cannot be modified.';

    private User $user;

    private Project $project;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-08-01 12:00:00');
        $this->travelTo($this->now);

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();

        Sanctum::actingAs($this->user);
    }

    public function test_creating_a_done_task_stamps_the_completion_time(): void
    {
        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Done->value,
        ])->assertStatus(Response::HTTP_CREATED);

        $this->assertSame($this->now->toIso8601String(), $response->json('data.completed_at'));
        $this->assertDatabaseHas('tasks', [
            'id' => $response->json('data.id'),
            'status' => TaskStatus::Done->value,
            'completed_at' => $this->now->toDateTimeString(),
        ]);
    }

    public function test_creating_an_unfinished_task_leaves_the_completion_time_null(): void
    {
        foreach ([TaskStatus::Todo, TaskStatus::InProgress] as $status) {
            $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks", [
                'title' => 'Fix the login redirect loop',
                'status' => $status->value,
            ])->assertStatus(Response::HTTP_CREATED);

            $response->assertJsonPath('data.completed_at', null);
            $this->assertDatabaseHas('tasks', [
                'id' => $response->json('data.id'),
                'completed_at' => null,
            ]);
        }
    }

    public function test_moving_a_task_to_done_stamps_the_completion_time(): void
    {
        $task = Task::factory()->for($this->project)->todo()->create();

        $this->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'status' => TaskStatus::Done->value,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TaskStatus::Done->value)
            ->assertJsonPath('data.completed_at', $this->now->toIso8601String());

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'completed_at' => $this->now->toDateTimeString(),
        ]);
    }

    public function test_moving_a_done_task_back_clears_the_completion_time(): void
    {
        foreach ([TaskStatus::Todo, TaskStatus::InProgress] as $status) {
            $task = Task::factory()->for($this->project)->done()->create();

            $this->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
                'status' => $status->value,
            ])
                ->assertOk()
                ->assertJsonPath('data.status', $status->value)
                ->assertJsonPath('data.completed_at', null);

            $this->assertDatabaseHas('tasks', ['id' => $task->id, 'completed_at' => null]);
        }
    }

    public function test_an_unrelated_edit_preserves_the_completion_time(): void
    {
        $completedAt = Carbon::parse('2026-07-20 09:30:00');
        $task = Task::factory()->for($this->project)->create([
            'status' => TaskStatus::Done,
            'completed_at' => $completedAt,
        ]);

        $this->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'title' => 'Renamed',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed')
            ->assertJsonPath('data.completed_at', $completedAt->toIso8601String());

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'completed_at' => $completedAt->toDateTimeString(),
        ]);
    }

    public function test_a_client_cannot_set_the_completion_time_on_update(): void
    {
        $task = Task::factory()->for($this->project)->todo()->create();

        $this->patchJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'completed_at' => '2020-01-01 00:00:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.completed_at', null);
    }

    public function test_an_archived_project_rejects_every_mutation_with_409(): void
    {
        $archived = Project::factory()->for($this->user)->archived()->create();
        $task = Task::factory()->for($archived)->create(['title' => 'Untouched']);

        $expected = [
            'success' => false,
            'message' => self::READ_ONLY_MESSAGE,
            'errors' => [],
        ];

        $this->postJson("/api/v1/projects/{$archived->id}/tasks", ['title' => 'Blocked'])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($expected);

        $this->patchJson("/api/v1/projects/{$archived->id}/tasks/{$task->id}", ['title' => 'Blocked'])
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($expected);

        $this->deleteJson("/api/v1/projects/{$archived->id}/tasks/{$task->id}")
            ->assertStatus(Response::HTTP_CONFLICT)
            ->assertExactJson($expected);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Untouched']);
        $this->assertNotSoftDeleted($task);
        $this->assertSame(1, Task::query()->where('project_id', $archived->id)->count());
    }

    public function test_a_completed_project_still_accepts_every_mutation(): void
    {
        $completed = Project::factory()->for($this->user)->completed()->create();
        $task = Task::factory()->for($completed)->create();

        $this->postJson("/api/v1/projects/{$completed->id}/tasks", ['title' => 'Allowed'])
            ->assertStatus(Response::HTTP_CREATED);

        $this->patchJson("/api/v1/projects/{$completed->id}/tasks/{$task->id}", ['title' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Renamed');

        $this->deleteJson("/api/v1/projects/{$completed->id}/tasks/{$task->id}")
            ->assertStatus(Response::HTTP_NO_CONTENT);

        $this->assertSoftDeleted($task);
    }
}
