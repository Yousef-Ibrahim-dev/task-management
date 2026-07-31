<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShowProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_an_unauthenticated_request(): void
    {
        $project = Project::factory()->create();

        $this->getJson("/api/v1/projects/{$project->id}")->assertUnauthorized();
    }

    public function test_it_returns_a_project_owned_by_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->completed()->create([
            'name' => 'Billing System Migration',
            'description' => 'Replace the legacy integration.',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Project retrieved successfully.')
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonPath('data.name', 'Billing System Migration')
            ->assertJsonPath('data.description', 'Replace the legacy integration.')
            ->assertJsonPath('data.status', ProjectStatus::Completed->value)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.deleted_at');
    }

    public function test_it_returns_404_for_a_project_owned_by_another_user(): void
    {
        $project = Project::factory()->for(User::factory())->create();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/projects/{$project->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_it_returns_404_for_a_project_that_does_not_exist(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/projects/999999')->assertNotFound();
    }

    public function test_it_returns_404_for_a_soft_deleted_project(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();
        $project->delete();

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/projects/{$project->id}")->assertNotFound();
    }
}
