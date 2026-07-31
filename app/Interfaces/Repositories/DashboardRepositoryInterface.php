<?php

declare(strict_types=1);

namespace App\Interfaces\Repositories;

interface DashboardRepositoryInterface
{
    /**
     * @return array{total_projects: int, active_projects: int, completed_projects: int, archived_projects: int, total_tasks: int, completed_tasks: int, pending_tasks: int, overdue_tasks: int}
     */
    public function summary(int $userId): array;
}
