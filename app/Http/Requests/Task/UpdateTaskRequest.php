<?php

declare(strict_types=1);

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    use TaskPayload;

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
            'due_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * @return array{title?: string, description?: string|null, status?: TaskStatus, priority?: TaskPriority, due_date?: string|null}
     */
    public function payload(): array
    {
        $data = $this->optionalAttributes();

        if ($this->has('title')) {
            $data['title'] = $this->string('title')->toString();
        }

        return $data;
    }
}
