<?php

declare(strict_types=1);

namespace App\Interfaces\Repositories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

interface ProjectRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Project>
     */
    public function paginateForUser(int $userId, ?int $perPage = null, ?ProjectStatus $status = null): LengthAwarePaginator;

    /**
     * @throws ModelNotFoundException<Project>
     */
    public function findOwnedByUser(int $projectId, int $userId): Project;

    /**
     * @param  array{name: string, description?: string|null, status?: ProjectStatus}  $data
     */
    public function createForUser(int $userId, array $data): Project;

    /**
     * @param  array{name?: string, description?: string|null, status?: ProjectStatus}  $data
     */
    public function update(Project $project, array $data): Project;

    public function delete(Project $project): void;
}
