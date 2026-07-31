<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Interfaces\Repositories\ProjectRepositoryInterface;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProjectService
{
    public function __construct(private readonly ProjectRepositoryInterface $projects) {}

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginateForUser(int $userId, ?int $perPage = null, ?ProjectStatus $status = null): LengthAwarePaginator
    {
        return $this->projects->paginateForUser($userId, $perPage, $status);
    }

    public function findForUser(int $projectId, int $userId): Project
    {
        return $this->projects->findOwnedByUser($projectId, $userId);
    }

    /**
     * @param  array{name: string, description?: string|null, status?: ProjectStatus}  $data
     */
    public function createForUser(int $userId, array $data): Project
    {
        return $this->projects->createForUser($userId, $data);
    }

    /**
     * @param  array{name?: string, description?: string|null, status?: ProjectStatus}  $data
     */
    public function updateForUser(int $projectId, int $userId, array $data): Project
    {
        return $this->projects->update(
            $this->projects->findOwnedByUser($projectId, $userId),
            $data,
        );
    }

    public function deleteForUser(int $projectId, int $userId): void
    {
        $this->projects->delete($this->projects->findOwnedByUser($projectId, $userId));
    }

    public function archiveForUser(int $projectId, int $userId): Project
    {
        return $this->changeStatus($projectId, $userId, ProjectStatus::Archived);
    }

    public function restoreForUser(int $projectId, int $userId): Project
    {
        return $this->changeStatus($projectId, $userId, ProjectStatus::Active);
    }

    private function changeStatus(int $projectId, int $userId, ProjectStatus $status): Project
    {
        $project = $this->projects->findOwnedByUser($projectId, $userId);

        if ($project->status === $status) {
            return $project;
        }

        return $this->projects->update($project, ['status' => $status]);
    }
}
