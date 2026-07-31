<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;

/**
 * Builds the part of a task payload that is optional on both create and update.
 * completed_at is absent on purpose: TaskService derives it from the status.
 */
trait TaskPayload
{
    /**
     * @return array{description?: string|null, status?: TaskStatus, priority?: TaskPriority, due_date?: string|null}
     */
    protected function optionalAttributes(): array
    {
        $data = [];

        if ($this->has('description')) {
            $description = $this->input('description');
            $data['description'] = is_string($description) ? $description : null;
        }

        $status = $this->enum('status', TaskStatus::class);

        if ($status !== null) {
            $data['status'] = $status;
        }

        $priority = $this->enum('priority', TaskPriority::class);

        if ($priority !== null) {
            $data['priority'] = $priority;
        }

        if ($this->has('due_date')) {
            $data['due_date'] = $this->date('due_date')?->toDateString();
        }

        return $data;
    }
}
