<?php

declare(strict_types=1);

namespace App\Services;

use App\Interfaces\Repositories\DashboardRepositoryInterface;

final class DashboardService
{
    public function __construct(private readonly DashboardRepositoryInterface $dashboard) {}

    /**
     * @return array{total_projects: int, active_projects: int, completed_projects: int, archived_projects: int, total_tasks: int, completed_tasks: int, pending_tasks: int, overdue_tasks: int}
     */
    public function summaryForUser(int $userId): array
    {
        return $this->dashboard->summary($userId);
    }
}
