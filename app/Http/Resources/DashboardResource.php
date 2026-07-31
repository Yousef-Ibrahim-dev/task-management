<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * @param  array{total_projects: int, active_projects: int, completed_projects: int, archived_projects: int, total_tasks: int, completed_tasks: int, pending_tasks: int, overdue_tasks: int}  $summary
     */
    public function __construct(private readonly array $summary)
    {
        parent::__construct($summary);
    }

    /**
     * Listed key by key so a counter added to the repository cannot reach the
     * response without a deliberate change here.
     *
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'total_projects' => $this->summary['total_projects'],
            'active_projects' => $this->summary['active_projects'],
            'completed_projects' => $this->summary['completed_projects'],
            'archived_projects' => $this->summary['archived_projects'],
            'total_tasks' => $this->summary['total_tasks'],
            'completed_tasks' => $this->summary['completed_tasks'],
            'pending_tasks' => $this->summary['pending_tasks'],
            'overdue_tasks' => $this->summary['overdue_tasks'],
        ];
    }
}
