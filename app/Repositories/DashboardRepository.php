<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Interfaces\Repositories\DashboardRepositoryInterface;
use App\Models\Project;
use App\Models\Task;

final class DashboardRepository implements DashboardRepositoryInterface
{
    public function __construct(
        private readonly Project $project,
        private readonly Task $task,
    ) {}

    /**
     * @return array{total_projects: int, active_projects: int, completed_projects: int, archived_projects: int, total_tasks: int, completed_tasks: int, pending_tasks: int, overdue_tasks: int}
     */
    public function summary(int $userId): array
    {
        $projects = $this->projectCounts($userId);
        $tasks = $this->taskCounts($userId);

        return [
            'total_projects' => $this->toInt($projects, 'total'),
            'active_projects' => $this->toInt($projects, 'active'),
            'completed_projects' => $this->toInt($projects, 'completed'),
            'archived_projects' => $this->toInt($projects, 'archived'),
            'total_tasks' => $this->toInt($tasks, 'total'),
            'completed_tasks' => $this->toInt($tasks, 'completed'),
            'pending_tasks' => $this->toInt($tasks, 'pending'),
            'overdue_tasks' => $this->toInt($tasks, 'overdue'),
        ];
    }

    /**
     * One row of conditional counts rather than four queries. The soft delete
     * scope survives toBase(), so trashed projects are already excluded.
     *
     * @return array<string, mixed>
     */
    private function projectCounts(int $userId): array
    {
        return (array) $this->project->newQuery()
            ->where('user_id', $userId)
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS active', [ProjectStatus::Active->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS completed', [ProjectStatus::Completed->value])
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS archived', [ProjectStatus::Archived->value])
            ->first();
    }

    /**
     * Tasks are reached through the project relation, which carries the owner
     * check and the project's soft delete scope, so a trashed project's tasks
     * never reach the totals.
     *
     * Today is bound from PHP rather than CURDATE() so the boundary follows the
     * application clock.
     *
     * @return array<string, mixed>
     */
    private function taskCounts(int $userId): array
    {
        $done = TaskStatus::Done->value;
        $today = now()->toDateString();

        return (array) $this->task->newQuery()
            ->whereRelation('project', 'user_id', $userId)
            ->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS completed', [$done])
            ->selectRaw('COUNT(CASE WHEN status <> ? THEN 1 END) AS pending', [$done])
            ->selectRaw('COUNT(CASE WHEN due_date < ? AND status <> ? THEN 1 END) AS overdue', [$today, $done])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toInt(array $row, string $key): int
    {
        return (int) ($row[$key] ?? 0);
    }
}
