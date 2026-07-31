<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\ProjectStatus;
use App\Interfaces\Repositories\ProjectRepositoryInterface;
use App\Models\Project;
use App\Traits\ResolvesPerPage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class ProjectRepository implements ProjectRepositoryInterface
{
    use ResolvesPerPage;

    public function __construct(private readonly Project $model) {}

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginateForUser(int $userId, ?int $perPage = null, ?ProjectStatus $status = null): LengthAwarePaginator
    {
        $query = $this->query()->where('user_id', $userId);

        if ($status !== null) {
            $query->where('status', $status);
        }

        // Without a deterministic order, MySQL is free to return the same row on
        // two different pages.
        return $query->orderByDesc('id')->paginate($this->resolvePerPage($perPage));
    }

    /**
     * @throws ModelNotFoundException<Project>
     */
    public function findOwnedByUser(int $projectId, int $userId): Project
    {
        return $this->query()
            ->whereKey($projectId)
            ->where('user_id', $userId)
            ->firstOrFail();
    }

    /**
     * @param  array{name: string, description?: string|null, status?: ProjectStatus}  $data
     */
    public function createForUser(int $userId, array $data): Project
    {
        // Assigned last so a caller cannot hand ownership to another user
        // through the payload.
        $data['user_id'] = $userId;

        return $this->query()->create($data);
    }

    /**
     * @param  array{name?: string, description?: string|null, status?: ProjectStatus}  $data
     */
    public function update(Project $project, array $data): Project
    {
        $project->update($data);

        return $project->refresh();
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }

    /**
     * @return Builder<Project>
     */
    private function query(): Builder
    {
        return $this->model->newQuery();
    }
}
