<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Interfaces\Repositories\DashboardRepositoryInterface;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ProjectSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-01 12:00:00'));

        $this->seed();
    }

    private function demoUser(): User
    {
        return User::query()->where('email', UserSeeder::DEMO_EMAIL)->firstOrFail();
    }

    private function secondUser(): User
    {
        return User::query()->where('email', UserSeeder::SECOND_EMAIL)->firstOrFail();
    }

    public function test_it_seeds_both_known_accounts_with_working_credentials(): void
    {
        $this->assertDatabaseCount('users', 2);

        foreach ([UserSeeder::DEMO_EMAIL, UserSeeder::SECOND_EMAIL] as $email) {
            $user = User::query()->where('email', $email)->firstOrFail();

            $this->assertNotSame(UserSeeder::PASSWORD, $user->password);
            $this->assertTrue(Hash::check(UserSeeder::PASSWORD, $user->password));
        }

        $this->assertSame('Demo User', $this->demoUser()->name);
        $this->assertSame('Second User', $this->secondUser()->name);
    }

    public function test_the_seeded_credentials_authenticate_through_the_api(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => UserSeeder::DEMO_EMAIL,
            'password' => UserSeeder::PASSWORD,
        ])
            ->assertOk()
            ->assertJsonPath('data.user.email', UserSeeder::DEMO_EMAIL);
    }

    public function test_the_demo_user_has_every_project_status(): void
    {
        $projects = Project::query()->where('user_id', $this->demoUser()->id)->get();

        $this->assertCount(4, $projects);
        $this->assertSame(2, $projects->where('status', ProjectStatus::Active)->count());
        $this->assertSame(1, $projects->where('status', ProjectStatus::Completed)->count());
        $this->assertSame(1, $projects->where('status', ProjectStatus::Archived)->count());
        $this->assertContains(ProjectSeeder::WEBSITE_REDESIGN, $projects->pluck('name')->all());
    }

    public function test_the_second_users_data_is_separate(): void
    {
        $demoProjects = Project::query()->where('user_id', $this->demoUser()->id)->pluck('name');
        $secondProjects = Project::query()->where('user_id', $this->secondUser()->id)->pluck('name');

        $this->assertCount(2, $secondProjects);
        $this->assertEmpty($demoProjects->intersect($secondProjects));

        $secondTasks = Task::query()->whereIn('project_id', Project::query()
            ->where('user_id', $this->secondUser()->id)->pluck('id'))->count();

        $this->assertSame(3, $secondTasks);
    }

    public function test_the_seeded_tasks_cover_every_status_and_priority(): void
    {
        $tasks = $this->demoTasks();

        foreach (TaskStatus::cases() as $status) {
            $this->assertGreaterThan(0, $tasks->where('status', $status)->count(), "missing {$status->value} task");
        }

        foreach (TaskPriority::cases() as $priority) {
            $this->assertGreaterThan(0, $tasks->where('priority', $priority)->count(), "missing {$priority->value} task");
        }
    }

    public function test_completion_timestamps_agree_with_status_everywhere(): void
    {
        Task::query()->get()->each(function (Task $task): void {
            $task->status === TaskStatus::Done
                ? $this->assertNotNull($task->completed_at, "{$task->title} is done without a completion time")
                : $this->assertNull($task->completed_at, "{$task->title} is unfinished but carries a completion time");
        });
    }

    public function test_it_seeds_the_due_date_scenarios_a_reviewer_needs(): void
    {
        $tasks = $this->demoTasks();
        $today = now()->startOfDay();

        $overdue = $tasks->filter(
            fn (Task $task): bool => $task->status !== TaskStatus::Done
                && $task->due_date !== null
                && $task->due_date->lt($today)
        );

        $dueToday = $tasks->filter(fn (Task $task): bool => $task->due_date?->isSameDay($today) === true);
        $future = $tasks->filter(fn (Task $task): bool => $task->due_date?->gt($today) === true);
        $undated = $tasks->filter(fn (Task $task): bool => $task->due_date === null);

        $lateButFinished = $tasks->filter(
            fn (Task $task): bool => $task->status === TaskStatus::Done
                && $task->due_date !== null
                && $task->due_date->lt($today)
        );

        $this->assertCount(3, $overdue);
        $this->assertCount(1, $dueToday);
        $this->assertGreaterThan(0, $future->count());
        $this->assertGreaterThan(0, $undated->count());
        $this->assertGreaterThan(0, $lateButFinished->count());
    }

    public function test_the_dashboard_reports_the_expected_totals_for_the_demo_user(): void
    {
        $summary = $this->app->make(DashboardRepositoryInterface::class)->summary($this->demoUser()->id);

        $this->assertSame([
            'total_projects' => 4,
            'active_projects' => 2,
            'completed_projects' => 1,
            'archived_projects' => 1,
            'total_tasks' => 14,
            'completed_tasks' => 6,
            'pending_tasks' => 8,
            'overdue_tasks' => 3,
        ], $summary);
    }

    public function test_the_dashboard_reports_the_expected_totals_for_the_second_user(): void
    {
        $summary = $this->app->make(DashboardRepositoryInterface::class)->summary($this->secondUser()->id);

        $this->assertSame([
            'total_projects' => 2,
            'active_projects' => 1,
            'completed_projects' => 0,
            'archived_projects' => 1,
            'total_tasks' => 3,
            'completed_tasks' => 1,
            'pending_tasks' => 2,
            'overdue_tasks' => 1,
        ], $summary);
    }

    public function test_no_seeded_row_is_soft_deleted(): void
    {
        $this->assertSame(0, Project::query()->onlyTrashed()->count());
        $this->assertSame(0, Task::query()->onlyTrashed()->count());
    }

    public function test_it_seeds_nothing_when_the_environment_is_production(): void
    {
        Task::query()->forceDelete();
        Project::query()->forceDelete();
        User::query()->delete();

        $this->app->detectEnvironment(fn (): string => 'production');

        // --force is what gets past Laravel's own production confirmation, so it
        // is the path the seeder's guard actually has to cover.
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_reseeding_creates_no_duplicates(): void
    {
        $before = [
            'users' => User::query()->count(),
            'projects' => Project::query()->count(),
            'tasks' => Task::query()->count(),
        ];

        $this->seed();

        $this->assertSame($before['users'], User::query()->count());
        $this->assertSame($before['projects'], Project::query()->count());
        $this->assertSame($before['tasks'], Task::query()->count());
        $this->assertTrue(Hash::check(UserSeeder::PASSWORD, $this->demoUser()->password));
    }

    /**
     * @return Collection<int, Task>
     */
    private function demoTasks(): Collection
    {
        return Task::query()
            ->whereIn('project_id', Project::query()->where('user_id', $this->demoUser()->id)->pluck('id'))
            ->get();
    }
}
