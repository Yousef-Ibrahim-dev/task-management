<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\ArchivedProjectIsReadOnlyException;
use App\Interfaces\Repositories\ProjectRepositoryInterface;
use App\Interfaces\Repositories\TaskRepositoryInterface;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class TaskServiceTest extends TestCase
{
    private const TASK_ID = 11;

    private const PROJECT_ID = 3;

    private const USER_ID = 7;

    private TaskRepositoryInterface&MockInterface $tasks;

    private ProjectRepositoryInterface&MockInterface $projects;

    private TaskService $service;

    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = Carbon::parse('2026-08-01 12:00:00');
        $this->travelTo($this->now);

        $this->tasks = Mockery::mock(TaskRepositoryInterface::class);
        $this->projects = Mockery::mock(ProjectRepositoryInterface::class);
        $this->service = new TaskService($this->tasks, $this->projects);
    }

    public function test_it_lists_tasks_through_the_repository(): void
    {
        $page = new LengthAwarePaginator([], 0, 15);

        $this->expectProjectResolved();

        $this->tasks->shouldReceive('paginateForProject')
            ->once()
            ->with(self::PROJECT_ID, self::USER_ID, 25, TaskStatus::Todo, TaskPriority::High, 'login')
            ->andReturn($page);

        $this->assertSame($page, $this->service->paginateForProject(
            self::PROJECT_ID,
            self::USER_ID,
            25,
            TaskStatus::Todo,
            TaskPriority::High,
            'login',
        ));
    }

    public function test_it_lists_tasks_inside_an_archived_project(): void
    {
        $page = new LengthAwarePaginator([], 0, 15);

        $this->expectProjectResolved(ProjectStatus::Archived);

        $this->tasks->shouldReceive('paginateForProject')->once()->andReturn($page);

        $this->assertSame($page, $this->service->paginateForProject(self::PROJECT_ID, self::USER_ID));
    }

    public function test_it_propagates_a_missing_project_when_listing(): void
    {
        $this->projects->shouldReceive('findOwnedByUser')
            ->once()
            ->with(self::PROJECT_ID, self::USER_ID)
            ->andThrow(new ModelNotFoundException);

        $this->tasks->shouldNotReceive('paginateForProject');

        $this->expectException(ModelNotFoundException::class);

        $this->service->paginateForProject(self::PROJECT_ID, self::USER_ID);
    }

    public function test_it_finds_a_task_through_the_repository(): void
    {
        $task = $this->task();

        $this->tasks->shouldReceive('findOwnedTask')
            ->once()
            ->with(self::TASK_ID, self::PROJECT_ID, self::USER_ID)
            ->andReturn($task);

        $this->assertSame($task, $this->service->findForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID));
    }

    public function test_it_views_a_task_inside_an_archived_project(): void
    {
        $task = $this->task();

        $this->tasks->shouldReceive('findOwnedTask')->once()->andReturn($task);

        $this->assertSame($task, $this->service->findForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID));
    }

    public function test_it_propagates_a_missing_task(): void
    {
        $this->tasks->shouldReceive('findOwnedTask')
            ->once()
            ->andThrow(new ModelNotFoundException);

        $this->expectException(ModelNotFoundException::class);

        $this->service->findForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID);
    }

    public function test_it_creates_a_todo_task_without_a_completion_time(): void
    {
        $this->expectProjectResolved();
        $this->expectCreate([
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Todo,
            'completed_at' => null,
        ]);

        $this->service->createForProject(self::PROJECT_ID, self::USER_ID, ['title' => 'Fix the login redirect loop']);
    }

    public function test_it_creates_an_in_progress_task_without_a_completion_time(): void
    {
        $this->expectProjectResolved();
        $this->expectCreate([
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::InProgress,
            'completed_at' => null,
        ]);

        $this->service->createForProject(self::PROJECT_ID, self::USER_ID, [
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::InProgress,
        ]);
    }

    public function test_it_stamps_a_completion_time_when_creating_a_done_task(): void
    {
        $this->expectProjectResolved();
        $this->expectCreate([
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Done,
            'completed_at' => $this->now,
        ]);

        $this->service->createForProject(self::PROJECT_ID, self::USER_ID, [
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Done,
        ]);
    }

    public function test_it_keeps_a_completion_time_supplied_on_create(): void
    {
        $supplied = Carbon::parse('2026-07-20 09:30:00');

        $this->expectProjectResolved();
        $this->expectCreate([
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Done,
            'completed_at' => $supplied,
        ]);

        $this->service->createForProject(self::PROJECT_ID, self::USER_ID, [
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Done,
            'completed_at' => $supplied,
        ]);
    }

    public function test_it_stamps_a_completion_time_when_a_task_becomes_done(): void
    {
        $task = $this->task(TaskStatus::Todo);

        $this->expectProjectResolved();
        $this->expectFind($task);
        $this->expectUpdate($task, ['status' => TaskStatus::Done, 'completed_at' => $this->now]);

        $this->service->updateForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID, ['status' => TaskStatus::Done]);
    }

    public function test_it_clears_the_completion_time_when_a_done_task_returns_to_todo(): void
    {
        $task = $this->task(TaskStatus::Done, Carbon::parse('2026-07-20 09:30:00'));

        $this->expectProjectResolved();
        $this->expectFind($task);
        $this->expectUpdate($task, ['status' => TaskStatus::Todo, 'completed_at' => null]);

        $this->service->updateForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID, ['status' => TaskStatus::Todo]);
    }

    public function test_it_clears_the_completion_time_when_a_done_task_returns_to_in_progress(): void
    {
        $task = $this->task(TaskStatus::Done, Carbon::parse('2026-07-20 09:30:00'));

        $this->expectProjectResolved();
        $this->expectFind($task);
        $this->expectUpdate($task, ['status' => TaskStatus::InProgress, 'completed_at' => null]);

        $this->service->updateForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID, ['status' => TaskStatus::InProgress]);
    }

    public function test_it_preserves_the_completion_time_when_status_is_untouched(): void
    {
        $completedAt = Carbon::parse('2026-07-20 09:30:00');
        $task = $this->task(TaskStatus::Done, $completedAt);

        $this->expectProjectResolved();
        $this->expectFind($task);
        $this->expectUpdate($task, ['title' => 'Renamed', 'completed_at' => $completedAt]);

        $this->service->updateForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID, ['title' => 'Renamed']);
    }

    public function test_it_keeps_an_unfinished_task_without_a_completion_time_on_an_unrelated_edit(): void
    {
        $task = $this->task(TaskStatus::Todo);

        $this->expectProjectResolved();
        $this->expectFind($task);
        $this->expectUpdate($task, ['title' => 'Renamed', 'completed_at' => null]);

        $this->service->updateForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID, ['title' => 'Renamed']);
    }

    public function test_it_repairs_a_done_task_that_lost_its_completion_time(): void
    {
        $task = $this->task(TaskStatus::Done);

        $this->expectProjectResolved();
        $this->expectFind($task);
        $this->expectUpdate($task, ['title' => 'Renamed', 'completed_at' => $this->now]);

        $this->service->updateForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID, ['title' => 'Renamed']);
    }

    public function test_it_deletes_a_task_it_first_resolved_by_owner(): void
    {
        $task = $this->task();

        $this->expectProjectResolved();
        $this->expectFind($task);

        $this->tasks->shouldReceive('delete')->once()->with($task);

        $this->service->deleteForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID);
    }

    public function test_it_allows_mutations_inside_a_completed_project(): void
    {
        $this->expectProjectResolved(ProjectStatus::Completed);
        $this->expectCreate([
            'title' => 'Fix the login redirect loop',
            'status' => TaskStatus::Todo,
            'completed_at' => null,
        ]);

        $this->service->createForProject(self::PROJECT_ID, self::USER_ID, ['title' => 'Fix the login redirect loop']);
    }

    public function test_it_refuses_to_create_inside_an_archived_project(): void
    {
        $this->expectProjectResolved(ProjectStatus::Archived);

        $this->tasks->shouldNotReceive('createForProject');

        $this->expectException(ArchivedProjectIsReadOnlyException::class);

        $this->service->createForProject(self::PROJECT_ID, self::USER_ID, ['title' => 'Blocked']);
    }

    public function test_it_refuses_to_update_inside_an_archived_project(): void
    {
        $this->expectProjectResolved(ProjectStatus::Archived);

        $this->tasks->shouldNotReceive('findOwnedTask');
        $this->tasks->shouldNotReceive('update');

        $this->expectException(ArchivedProjectIsReadOnlyException::class);

        $this->service->updateForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID, ['title' => 'Blocked']);
    }

    public function test_it_refuses_to_delete_inside_an_archived_project(): void
    {
        $this->expectProjectResolved(ProjectStatus::Archived);

        $this->tasks->shouldNotReceive('findOwnedTask');
        $this->tasks->shouldNotReceive('delete');

        $this->expectException(ArchivedProjectIsReadOnlyException::class);

        $this->service->deleteForUser(self::TASK_ID, self::PROJECT_ID, self::USER_ID);
    }

    private function expectProjectResolved(ProjectStatus $status = ProjectStatus::Active): void
    {
        $this->projects->shouldReceive('findOwnedByUser')
            ->once()
            ->with(self::PROJECT_ID, self::USER_ID)
            ->andReturn(new Project(['name' => 'Billing System Migration', 'status' => $status]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function expectCreate(array $data): void
    {
        $this->tasks->shouldReceive('createForProject')
            ->once()
            ->with(self::PROJECT_ID, self::USER_ID, $data)
            ->andReturn($this->task());
    }

    private function expectFind(Task $task): void
    {
        $this->tasks->shouldReceive('findOwnedTask')
            ->once()
            ->with(self::TASK_ID, self::PROJECT_ID, self::USER_ID)
            ->andReturn($task);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function expectUpdate(Task $task, array $data): void
    {
        $this->tasks->shouldReceive('update')
            ->once()
            ->with($task, $data)
            ->andReturn($task);
    }

    private function task(TaskStatus $status = TaskStatus::Todo, ?Carbon $completedAt = null): Task
    {
        return new Task([
            'title' => 'Fix the login redirect loop',
            'status' => $status,
            'completed_at' => $completedAt,
        ]);
    }
}
