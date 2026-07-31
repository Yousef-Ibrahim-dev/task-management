<?php

declare(strict_types=1);

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProjectRequest extends FormRequest
{
    /**
     * per_page has no upper bound here on purpose: the repository clamps it to
     * config('api.pagination.max') rather than rejecting the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
        ];
    }

    public function perPage(): ?int
    {
        return $this->has('per_page') ? $this->integer('per_page') : null;
    }

    public function status(): ?ProjectStatus
    {
        return $this->enum('status', ProjectStatus::class);
    }
}
