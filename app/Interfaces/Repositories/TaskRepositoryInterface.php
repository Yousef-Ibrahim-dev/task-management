<?php

declare(strict_types=1);

namespace App\Interfaces\Repositories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Task reads are always anchored to an owned, non-deleted project: there is no
 * method here that resolves a task from its own id alone.
 */
interface TaskRepositoryInterface
{
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
    ): LengthAwarePaginator;

    /**
     * @throws ModelNotFoundException<Task>
     */
    public function findOwnedTask(int $taskId, int $projectId, int $userId): Task;

    /**
     * @param  array{title: string, description?: string|null, status?: TaskStatus, priority?: TaskPriority, due_date?: string|DateTimeInterface|null, completed_at?: DateTimeInterface|null}  $data
     */
    public function createForProject(int $projectId, int $userId, array $data): Task;

    /**
     * @param  array{title?: string, description?: string|null, status?: TaskStatus, priority?: TaskPriority, due_date?: string|DateTimeInterface|null, completed_at?: DateTimeInterface|null}  $data
     */
    public function update(Task $task, array $data): Task;

    public function delete(Task $task): void;
}
