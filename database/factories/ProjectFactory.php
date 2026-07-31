<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => ucfirst(fake()->words(3, true)),
            'description' => fake()->optional(0.8)->sentence(),
            'status' => ProjectStatus::Active,
        ];
    }

    public function active(): static
    {
        return $this->state(static fn (): array => [
            'status' => ProjectStatus::Active,
        ]);
    }

    public function archived(): static
    {
        return $this->state(static fn (): array => [
            'status' => ProjectStatus::Archived,
        ]);
    }
}
