<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Interfaces\Repositories\TaskRepositoryInterface;
use App\Models\Project;
use App\Models\Task;
use App\Traits\ResolvesPerPage;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class TaskRepository implements TaskRepositoryInterface
{
    use ResolvesPerPage;

    public function __construct(
        private readonly Task $model,
        private readonly Project $project,
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
        $query = $this->ownedQuery($projectId, $userId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        if ($priority !== null) {
            $query->where('priority', $priority);
        }

        $term = trim($search ?? '');

        if ($term !== '') {
            $query->where('title', 'like', '%'.$this->escapeLikeWildcards($term).'%');
        }

        return $query->orderByDesc('id')->paginate($this->resolvePerPage($perPage));
    }

    /**
     * @throws ModelNotFoundException<Task>
     */
    public function findOwnedTask(int $taskId, int $projectId, int $userId): Task
    {
        return $this->ownedQuery($projectId, $userId)->whereKey($taskId)->firstOrFail();
    }

    /**
     * @param  array{title: string, description?: string|null, status?: TaskStatus, priority?: TaskPriority, due_date?: string|DateTimeInterface|null, completed_at?: DateTimeInterface|null}  $data
     */
    public function createForProject(int $projectId, int $userId, array $data): Task
    {
        $this->requireOwnedProject($projectId, $userId);

        // Assigned last so a payload cannot move the task into another project.
        $data['project_id'] = $projectId;

        return $this->model->newQuery()->create($data);
    }

    /**
     * @param  array{title?: string, description?: string|null, status?: TaskStatus, priority?: TaskPriority, due_date?: string|DateTimeInterface|null, completed_at?: DateTimeInterface|null}  $data
     */
    public function update(Task $task, array $data): Task
    {
        $task->update($data);

        return $task->refresh();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    /**
     * Ownership is a join condition, not a PHP comparison. The relation carries
     * the project's soft delete scope, so a trashed project hides its tasks.
     *
     * @return Builder<Task>
     */
    private function ownedQuery(int $projectId, int $userId): Builder
    {
        return $this->model->newQuery()
            ->where('project_id', $projectId)
            ->whereRelation('project', 'user_id', $userId);
    }

    /**
     * @throws ModelNotFoundException<Project>
     */
    private function requireOwnedProject(int $projectId, int $userId): void
    {
        $exists = $this->project->newQuery()
            ->whereKey($projectId)
            ->where('user_id', $userId)
            ->exists();

        if (! $exists) {
            throw (new ModelNotFoundException)->setModel(Project::class, [$projectId]);
        }
    }

    /**
     * Without this a search for "50%" would match every title.
     */
    private function escapeLikeWildcards(string $term): string
    {
        return addcslashes($term, '%_\\');
    }
}
