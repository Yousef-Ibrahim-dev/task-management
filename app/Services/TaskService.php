<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\ArchivedProjectIsReadOnlyException;
use App\Interfaces\Repositories\ProjectRepositoryInterface;
use App\Interfaces\Repositories\TaskRepositoryInterface;
use App\Models\Project;
use App\Models\Task;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class TaskService
{
    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly ProjectRepositoryInterface $projects,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginateForProject(
        int $projectId,
        int $userId,
        ?int $perPage = null,
        ?TaskStatus $status = null,
        ?TaskPriority $priority = null,
        ?string $search = null,
    ): LengthAwarePaginator {
        // Resolved for the failure it raises: an unknown or unowned project must
        // read as not found rather than as an empty page.
        $this->projects->findOwnedByUser($projectId, $userId);

        return $this->tasks->paginateForProject($projectId, $userId, $perPage, $status, $priority, $search);
    }

    public function findForUser(int $taskId, int $projectId, int $userId): Task
    {
        return $this->tasks->findOwnedTask($taskId, $projectId, $userId);
    }

    /**
     * @param  array{title: string, description?: string|null, status?: TaskStatus, priority?: TaskPriority, due_date?: string|DateTimeInterface|null, completed_at?: DateTimeInterface|null}  $data
     */
    public function createForProject(int $projectId, int $userId, array $data): Task
    {
        $this->guardAgainstArchived($this->projects->findOwnedByUser($projectId, $userId));

        $data['status'] ??= TaskStatus::Todo;
        $data['completed_at'] = $this->completedAt($data['status'], $data['completed_at'] ?? null);

        return $this->tasks->createForProject($projectId, $userId, $data);
    }

    /**
     * @param  array{title?: string, description?: string|null, status?: TaskStatus, priority?: TaskPriority, due_date?: string|DateTimeInterface|null, completed_at?: DateTimeInterface|null}  $data
     */
    public function updateForUser(int $taskId, int $projectId, int $userId, array $data): Task
    {
        $this->guardAgainstArchived($this->projects->findOwnedByUser($projectId, $userId));

        $task = $this->tasks->findOwnedTask($taskId, $projectId, $userId);

        $data['completed_at'] = $this->completedAt(
            $data['status'] ?? $task->status,
            $data['completed_at'] ?? $task->completed_at,
        );

        return $this->tasks->update($task, $data);
    }

    public function deleteForUser(int $taskId, int $projectId, int $userId): void
    {
        $this->guardAgainstArchived($this->projects->findOwnedByUser($projectId, $userId));

        $this->tasks->delete($this->tasks->findOwnedTask($taskId, $projectId, $userId));
    }

    /**
     * @throws ArchivedProjectIsReadOnlyException
     */
    private function guardAgainstArchived(Project $project): void
    {
        if ($project->status === ProjectStatus::Archived) {
            throw new ArchivedProjectIsReadOnlyException;
        }
    }

    /**
     * A completion timestamp only exists while the task is done, so any other
     * status clears it and a done task always carries one.
     */
    private function completedAt(TaskStatus $status, ?DateTimeInterface $current): ?DateTimeInterface
    {
        if ($status !== TaskStatus::Done) {
            return null;
        }

        return $current ?? now();
    }
}
