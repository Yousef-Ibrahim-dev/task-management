<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
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
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function perPage(): ?int
    {
        return $this->has('per_page') ? $this->integer('per_page') : null;
    }

    public function status(): ?TaskStatus
    {
        return $this->enum('status', TaskStatus::class);
    }

    public function priority(): ?TaskPriority
    {
        return $this->enum('priority', TaskPriority::class);
    }

    public function search(): ?string
    {
        $search = trim($this->string('search')->toString());

        return $search === '' ? null : $search;
    }
}
