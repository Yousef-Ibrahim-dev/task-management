<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

final class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function archive(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    public function restoreStatus(User $user, Project $project): bool
    {
        return $this->owns($user, $project);
    }

    private function owns(User $user, Project $project): bool
    {
        return $project->user_id === $user->id;
    }
}
