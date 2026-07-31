<?php

declare(strict_types=1);

namespace Tests\Feature\Repositories;

use App\Enums\TaskStatus;
use App\Interfaces\Repositories\DashboardRepositoryInterface;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Repositories\DashboardRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private DashboardRepositoryInterface $repository;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-01 12:00:00'));

        $this->repository = $this->app->make(DashboardRepositoryInterface::class);
        $this->user = User::factory()->create();
    }

    public function test_the_container_resolves_the_bound_implementation(): void
    {
        $this->assertInstanceOf(DashboardRepository::class, $this->repository);
    }

    public function test_it_returns_zeroes_for_a_user_with_no_data(): void
    {
        $this->assertSame([
            'total_projects' => 0,
            'active_projects' => 0,
            'completed_projects' => 0,
            'archived_projects' => 0,
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'pending_tasks' => 0,
            'overdue_tasks' => 0,
        ], $this->repository->summary($this->user->id));
    }

    public function test_it_counts_projects_by_status(): void
    {
        Project::factory()->count(2)->for($this->user)->create();
        Project::factory()->count(3)->for($this->user)->completed()->create();
        Project::factory()->count(4)->for($this->user)->archived()->create();

        $summary = $this->repository->summary($this->user->id);

        $this->assertSame(9, $summary['total_projects']);
        $this->assertSame(2, $summary['active_projects']);
        $this->assertSame(3, $summary['completed_projects']);
        $this->assertSame(4, $summary['archived_projects']);
    }

    public function test_it_excludes_soft_deleted_projects(): void
    {
        Project::factory()->count(2)->for($this->user)->create();
        Project::factory()->for($this->user)->completed()->create()->delete();

        $summary = $this->repository->summary($this->user->id);

        $this->assertSame(2, $summary['total_projects']);
        $this->assertSame(0, $summary['completed_projects']);
    }

    public function test_it_counts_tasks_by_completion(): void
    {
        $project = Project::factory()->for($this->user)->create();

        Task::factory()->count(2)->for($project)->done()->create();
        Task::factory()->count(3)->for($project)->todo()->create();
        Task::factory()->count(4)->for($project)->inProgress()->create();

        $summary = $this->repository->summary($this->user->id);

        $this->assertSame(9, $summary['total_tasks']);
        $this->assertSame(2, $summary['completed_tasks']);
        $this->assertSame(7, $summary['pending_tasks']);
    }

    public function test_pending_and_completed_always_account_for_every_task(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Task::factory()->count(12)->for($project)->create();

        $summary = $this->repository->summary($this->user->id);

        $this->assertSame(
            $summary['total_tasks'],
            $summary['completed_tasks'] + $summary['pending_tasks'],
        );
    }

    public function test_it_counts_only_unfinished_tasks_due_before_today_as_overdue(): void
    {
        $project = Project::factory()->for($this->user)->create();

        Task::factory()->count(2)->for($project)->todo()->create(['due_date' => '2026-07-25']);
        Task::factory()->for($project)->inProgress()->create(['due_date' => '2026-07-31']);

        // Due today is not yet overdue.
        Task::factory()->for($project)->todo()->create(['due_date' => '2026-08-01']);
        Task::factory()->for($project)->todo()->create(['due_date' => '2026-08-05']);
        Task::factory()->for($project)->todo()->create(['due_date' => null]);
        // Finished work is never overdue, however late it was.
        Task::factory()->for($project)->done()->create(['due_date' => '2026-07-01']);

        $this->assertSame(3, $this->repository->summary($this->user->id)['overdue_tasks']);
    }

    public function test_it_excludes_tasks_under_soft_deleted_projects(): void
    {
        $live = Project::factory()->for($this->user)->create();
        $trashed = Project::factory()->for($this->user)->create();

        Task::factory()->count(2)->for($live)->todo()->create(['due_date' => '2026-07-01']);
        Task::factory()->count(5)->for($trashed)->todo()->create(['due_date' => '2026-07-01']);

        $trashed->delete();

        $summary = $this->repository->summary($this->user->id);

        $this->assertSame(2, $summary['total_tasks']);
        $this->assertSame(2, $summary['pending_tasks']);
        $this->assertSame(2, $summary['overdue_tasks']);
    }

    public function test_it_excludes_soft_deleted_tasks(): void
    {
        $project = Project::factory()->for($this->user)->create();

        // Due dates are pinned because the factory otherwise randomises them,
        // which would let a surviving task drift into the overdue count.
        Task::factory()->count(2)->for($project)->todo()->create(['due_date' => null]);
        Task::factory()->for($project)->todo()->create(['due_date' => '2026-07-01'])->delete();

        $summary = $this->repository->summary($this->user->id);

        $this->assertSame(2, $summary['total_tasks']);
        $this->assertSame(0, $summary['overdue_tasks']);
    }

    public function test_it_ignores_another_users_projects_and_tasks(): void
    {
        $stranger = User::factory()->create();
        $strangerProject = Project::factory()->for($stranger)->archived()->create();
        Task::factory()->count(6)->for($strangerProject)->done()->create();

        $project = Project::factory()->for($this->user)->create();
        Task::factory()->for($project)->todo()->create();

        $summary = $this->repository->summary($this->user->id);

        $this->assertSame(1, $summary['total_projects']);
        $this->assertSame(0, $summary['archived_projects']);
        $this->assertSame(1, $summary['total_tasks']);
        $this->assertSame(0, $summary['completed_tasks']);
    }

    public function test_it_answers_with_a_fixed_number_of_queries(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Task::factory()->count(20)->for($project)->create();
        Project::factory()->count(10)->for($this->user)->create();

        DB::enableQueryLog();

        $this->repository->summary($this->user->id);

        // Two aggregates regardless of how many projects or tasks exist.
        $this->assertCount(2, DB::getQueryLog());

        DB::disableQueryLog();
    }

    public function test_the_status_counters_use_the_enum_values(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Task::factory()->for($project)->create(['status' => TaskStatus::Done]);

        $this->assertSame(1, $this->repository->summary($this->user->id)['completed_tasks']);
    }
}
