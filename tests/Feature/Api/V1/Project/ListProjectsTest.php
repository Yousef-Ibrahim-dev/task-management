<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Project;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListProjectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_an_unauthenticated_request(): void
    {
        $this->getJson('/api/v1/projects')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_it_returns_only_the_projects_of_the_authenticated_user(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->for($user)->create();
        Project::factory()->count(3)->for(User::factory())->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/projects')->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Projects retrieved successfully.')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_it_filters_the_listing_by_status(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->for($user)->create();
        Project::factory()->count(3)->for($user)->archived()->create();
        Project::factory()->count(4)->for($user)->completed()->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects?status=archived')
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->getJson('/api/v1/projects?status=completed')
            ->assertOk()
            ->assertJsonPath('meta.total', 4);

        $this->getJson('/api/v1/projects?status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_it_rejects_a_status_outside_the_enum(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/projects?status=deleted')
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['success', 'message', 'errors' => ['status']]);
    }

    public function test_it_paginates_and_exposes_the_envelope_metadata(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(12)->for($user)->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/projects?per_page=5&page=2')->assertOk();

        $response->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.total', 12)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['current_page', 'per_page', 'last_page', 'total'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    public function test_it_clamps_an_oversized_page_size_instead_of_rejecting_it(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(3)->for($user)->create();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects?per_page=5000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', (int) config('api.pagination.max'));
    }

    public function test_it_excludes_soft_deleted_projects(): void
    {
        $user = User::factory()->create();
        Project::factory()->count(2)->for($user)->create();
        Project::factory()->for($user)->create()->delete();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/projects')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_it_never_exposes_the_owner_or_the_deletion_timestamp(): void
    {
        $user = User::factory()->create();
        Project::factory()->for($user)->create(['status' => ProjectStatus::Active]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/projects')->assertOk();

        $response->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.deleted_at')
            ->assertJsonStructure([
                'data' => [['id', 'name', 'description', 'status', 'created_at', 'updated_at']],
            ]);
    }
}
