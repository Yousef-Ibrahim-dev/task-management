<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;

/**
 * Turns validated input into the shape ProjectService expects. Shared so the
 * store and update requests state their rules independently without repeating
 * the mapping.
 */
trait ProjectPayload
{
    /**
     * @return array{name: string, description?: string|null, status?: ProjectStatus}
     */
    public function payload(): array
    {
        $data = ['name' => $this->string('name')->toString()];

        if ($this->has('description')) {
            $description = $this->input('description');
            $data['description'] = is_string($description) ? $description : null;
        }

        $status = $this->enum('status', ProjectStatus::class);

        if ($status !== null) {
            $data['status'] = $status;
        }

        return $data;
    }
}
