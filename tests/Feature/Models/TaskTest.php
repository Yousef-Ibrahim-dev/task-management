<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_task_can_be_created_for_a_project(): void
    {
        $project = Project::factory()->create();

        $task = Task::factory()->for($project)->todo()->create([
            'title' => 'Fix the login redirect loop',
            'priority' => TaskPriority::High,
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'project_id' => $project->id,
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::High->value,
        ]);
    }

    public function test_a_task_belongs_to_a_project(): void
    {
        $project = Project::factory()->create();

        $task = Task::factory()->for($project)->create()->load('project');

        $this->assertTrue($task->project->is($project));
    }

    public function test_status_and_priority_are_cast_to_enums(): void
    {
        $task = Task::factory()->create([
            'status' => TaskStatus::InProgress,
            'priority' => TaskPriority::Low,
        ])->fresh();

        $this->assertSame(TaskStatus::InProgress, $task?->status);
        $this->assertSame(TaskPriority::Low, $task?->priority);
        $this->assertDatabaseHas('tasks', [
            'status' => 'in_progress',
            'priority' => 'low',
        ]);
    }

    public function test_due_date_and_completed_at_are_cast_to_dates(): void
    {
        $task = Task::factory()->create([
            'due_date' => '2026-09-15',
            'completed_at' => '2026-09-14 10:30:00',
        ])->fresh();

        $this->assertInstanceOf(Carbon::class, $task?->due_date);
        $this->assertInstanceOf(Carbon::class, $task?->completed_at);
        $this->assertSame('2026-09-15', $task?->due_date->toDateString());
        $this->assertSame('2026-09-14 10:30:00', $task?->completed_at->toDateTimeString());
    }

    public function test_due_date_and_completed_at_are_optional(): void
    {
        $task = Task::factory()->todo()->create(['due_date' => null]);

        $this->assertNull($task->due_date);
        $this->assertNull($task->completed_at);
    }

    public function test_deleting_a_project_deletes_its_tasks(): void
    {
        $project = Project::factory()->has(Task::factory()->count(3))->create();

        $project->delete();

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_deleting_a_user_deletes_tasks_through_the_project(): void
    {
        $project = Project::factory()->has(Task::factory()->count(2))->create();

        $project->user()->delete();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_the_factory_never_completes_an_unfinished_task(): void
    {
        Task::factory()->count(30)->for(Project::factory())->create();

        Task::all()->each(function (Task $task): void {
            $task->status === TaskStatus::Done
                ? $this->assertNotNull($task->completed_at)
                : $this->assertNull($task->completed_at);
        });
    }

    public function test_the_done_state_records_a_completion_time(): void
    {
        $task = Task::factory()->done()->create();

        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertNotNull($task->completed_at);
    }
}
