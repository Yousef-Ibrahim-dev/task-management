<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Due dates are expressed relative to the seeding moment, so "overdue" and
 * "due today" keep meaning whenever the dataset is rebuilt.
 */
class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $demo = $this->user(UserSeeder::DEMO_EMAIL);
        $second = $this->user(UserSeeder::SECOND_EMAIL);

        $this->seedProject($demo, ProjectSeeder::WEBSITE_REDESIGN, $this->websiteRedesignTasks());
        $this->seedProject($demo, ProjectSeeder::MOBILE_APP_LAUNCH, $this->mobileAppLaunchTasks());
        $this->seedProject($demo, ProjectSeeder::INTERNAL_REPORTING, $this->internalReportingTasks());
        $this->seedProject($demo, ProjectSeeder::LEGACY_MIGRATION, $this->legacyMigrationTasks());

        $this->seedProject($second, ProjectSeeder::CLIENT_PORTAL, $this->clientPortalTasks());
        $this->seedProject($second, ProjectSeeder::SUPPORT_HANDBOOK, $this->supportHandbookTasks());
    }

    /**
     * @return list<array{title: string, status: TaskStatus, priority: TaskPriority, due_date: string|null, completed_at: string|null}>
     */
    private function websiteRedesignTasks(): array
    {
        return [
            $this->done('Audit the current information architecture', TaskPriority::Medium, $this->daysAgo(14), $this->daysAgo(12)),
            $this->pending('Rebuild the navigation component', TaskStatus::InProgress, TaskPriority::High, $this->daysAhead(7)),
            $this->pending('Fix the login redirect loop', TaskStatus::Todo, TaskPriority::High, $this->daysAgo(3)),
            $this->pending('Write copy for the pricing page', TaskStatus::Todo, TaskPriority::Low, $this->today()),
            $this->pending('Remove the legacy stylesheet', TaskStatus::Todo, TaskPriority::Medium, null),
        ];
    }

    /**
     * @return list<array{title: string, status: TaskStatus, priority: TaskPriority, due_date: string|null, completed_at: string|null}>
     */
    private function mobileAppLaunchTasks(): array
    {
        return [
            $this->pending('Prepare the App Store listing', TaskStatus::Todo, TaskPriority::Medium, $this->daysAhead(21)),
            $this->pending('Fix the crash on cold start', TaskStatus::InProgress, TaskPriority::High, $this->daysAgo(1)),
            $this->pending('Add offline caching for the task list', TaskStatus::Todo, TaskPriority::Low, $this->daysAhead(30)),
            $this->done('Ship the beta build to testers', TaskPriority::High, $this->daysAgo(7), $this->daysAgo(5)),
        ];
    }

    /**
     * @return list<array{title: string, status: TaskStatus, priority: TaskPriority, due_date: string|null, completed_at: string|null}>
     */
    private function internalReportingTasks(): array
    {
        return [
            $this->done('Migrate reports off the primary database', TaskPriority::High, $this->daysAgo(30), $this->daysAgo(25)),
            $this->done('Document the export format', TaskPriority::Low, $this->daysAgo(20), $this->daysAgo(18)),
            $this->done('Schedule the weekly summary', TaskPriority::Medium, $this->daysAgo(15), $this->daysAgo(15)),
        ];
    }

    /**
     * @return list<array{title: string, status: TaskStatus, priority: TaskPriority, due_date: string|null, completed_at: string|null}>
     */
    private function legacyMigrationTasks(): array
    {
        return [
            $this->done('Inventory the remaining endpoints', TaskPriority::Medium, $this->daysAgo(60), $this->daysAgo(55)),
            $this->pending('Decommission the old server', TaskStatus::Todo, TaskPriority::Low, $this->daysAgo(45)),
        ];
    }

    /**
     * @return list<array{title: string, status: TaskStatus, priority: TaskPriority, due_date: string|null, completed_at: string|null}>
     */
    private function clientPortalTasks(): array
    {
        return [
            $this->pending('Draft the onboarding checklist', TaskStatus::Todo, TaskPriority::Medium, $this->daysAhead(5)),
            $this->done('Approve the new logo', TaskPriority::Low, $this->daysAgo(2), $this->daysAgo(1)),
        ];
    }

    /**
     * @return list<array{title: string, status: TaskStatus, priority: TaskPriority, due_date: string|null, completed_at: string|null}>
     */
    private function supportHandbookTasks(): array
    {
        return [
            $this->pending('Collect the top ten questions', TaskStatus::Todo, TaskPriority::High, $this->daysAgo(2)),
        ];
    }

    /**
     * @return array{title: string, status: TaskStatus, priority: TaskPriority, due_date: string|null, completed_at: string|null}
     */
    private function done(string $title, TaskPriority $priority, string $dueDate, string $completedAt): array
    {
        return [
            'title' => $title,
            'status' => TaskStatus::Done,
            'priority' => $priority,
            'due_date' => $dueDate,
            'completed_at' => $completedAt,
        ];
    }

    /**
     * Building unfinished rows through here is what keeps completed_at null for
     * everything that is not done.
     *
     * @return array{title: string, status: TaskStatus, priority: TaskPriority, due_date: string|null, completed_at: string|null}
     */
    private function pending(string $title, TaskStatus $status, TaskPriority $priority, ?string $dueDate): array
    {
        return [
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'due_date' => $dueDate,
            'completed_at' => null,
        ];
    }

    /**
     * @param  list<array{title: string, status: TaskStatus, priority: TaskPriority, due_date: string|null, completed_at: string|null}>  $rows
     */
    private function seedProject(User $user, string $projectName, array $rows): void
    {
        $project = Project::query()
            ->where('user_id', $user->id)
            ->where('name', $projectName)
            ->firstOrFail();

        foreach ($rows as $row) {
            Task::query()->updateOrCreate(
                ['project_id' => $project->id, 'title' => $row['title']],
                [
                    'status' => $row['status'],
                    'priority' => $row['priority'],
                    'due_date' => $row['due_date'],
                    'completed_at' => $row['completed_at'],
                ],
            );
        }
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function today(): string
    {
        return now()->toDateString();
    }

    private function daysAgo(int $days): string
    {
        return now()->subDays($days)->toDateString();
    }

    private function daysAhead(int $days): string
    {
        return now()->addDays($days)->toDateString();
    }
}
