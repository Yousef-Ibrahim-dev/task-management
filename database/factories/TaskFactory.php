<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $status = fake()->randomElement(TaskStatus::cases());

        return [
            'project_id' => Project::factory(),
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->optional(0.6)->sentence(),
            'status' => $status,
            'priority' => fake()->randomElement(TaskPriority::cases()),
            'due_date' => fake()
                ->optional(0.7)
                ->dateTimeBetween('-2 weeks', '+2 months'),
            'completed_at' => $status === TaskStatus::Done
                ? fake()->dateTimeBetween('-1 month', 'now')
                : null,
        ];
    }

    public function todo(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Todo,
            'completed_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::InProgress,
            'completed_at' => null,
        ]);
    }

    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => TaskStatus::Done,
            'completed_at' => fake()->dateTimeBetween('-1 month', 'now'),
        ]);
    }

    public function lowPriority(): static
    {
        return $this->state(fn (): array => [
            'priority' => TaskPriority::Low,
        ]);
    }

    public function mediumPriority(): static
    {
        return $this->state(fn (): array => [
            'priority' => TaskPriority::Medium,
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (): array => [
            'priority' => TaskPriority::High,
        ]);
    }
}
