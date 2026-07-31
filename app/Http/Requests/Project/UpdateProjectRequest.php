<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    use ProjectPayload;

    /**
     * PUT replaces the project, so a name is required here as well. Declared
     * separately from the store rules so the two can diverge.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(ProjectStatus::class)],
        ];
    }

    use ProjectPayload;
}
