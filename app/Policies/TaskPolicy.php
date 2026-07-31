<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

final class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->owns($user, $task);
    }

    /**
     * A task is owned through its project. Reading an unloaded relation here
     * would either lazy load or hide a query behind authorization, so an
     * unloaded relation is a denial rather than a lookup.
     */
    private function owns(User $user, Task $task): bool
    {
        if (! $task->relationLoaded('project')) {
            return false;
        }

        return $task->project->user_id === $user->id;
    }
}
