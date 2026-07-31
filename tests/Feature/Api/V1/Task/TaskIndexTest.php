<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->for($this->user)->create();
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson("/api/v1/projects/{$this->project->id}/tasks")
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_it_lists_only_the_tasks_of_the_requested_project(): void
    {
        Task::factory()->count(3)->for($this->project)->create();
        Task::factory()->count(4)->for(Project::factory()->for($this->user))->create();
        Task::factory()->count(5)->for(Project::factory()->for(User::factory()))->create();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tasks retrieved successfully.')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonCount(3, 'data');
    }

    public function test_it_returns_404_for_another_users_project(): void
    {
        $strangerProject = Project::factory()->for(User::factory())->create();
        Task::factory()->count(2)->for($strangerProject)->create();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$strangerProject->id}/tasks")
            ->assertNotFound()
            ->assertJsonPath('message', 'Resource not found.');
    }

    public function test_it_returns_404_for_a_soft_deleted_project(): void
    {
        Task::factory()->count(2)->for($this->project)->create();
        $this->project->delete();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks")->assertNotFound();
    }

    public function test_it_excludes_soft_deleted_tasks(): void
    {
        Task::factory()->count(2)->for($this->project)->create();
        Task::factory()->for($this->project)->create()->delete();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks")
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_it_filters_by_status_and_priority(): void
    {
        // Priorities are pinned because the factory otherwise randomises them,
        // which would let the done tasks bleed into the high-priority count.
        Task::factory()->count(2)->for($this->project)->todo()->lowPriority()->create();
        Task::factory()->count(3)->for($this->project)->done()->lowPriority()->create();
        Task::factory()->count(4)->for($this->project)->todo()->highPriority()->create();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?status=".TaskStatus::Done->value)
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?priority=".TaskPriority::High->value)
            ->assertOk()
            ->assertJsonPath('meta.total', 4);
    }

    public function test_it_searches_titles_only(): void
    {
        Task::factory()->for($this->project)->create(['title' => 'Fix the login redirect loop']);
        Task::factory()->for($this->project)->create([
            'title' => 'Document the export format',
            'description' => 'The login screen is unrelated.',
        ]);

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?search=login")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Fix the login redirect loop');
    }

    public function test_it_trims_the_search_term_and_ignores_a_blank_one(): void
    {
        Task::factory()->for($this->project)->create(['title' => 'Fix the login redirect loop']);
        Task::factory()->count(2)->for($this->project)->create(['title' => 'Something else']);

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?search=".urlencode('   login   '))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?search=".urlencode('   '))
            ->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?search=")
            ->assertOk()
            ->assertJsonPath('meta.total', 3);
    }

    public function test_it_combines_every_filter_with_and(): void
    {
        Task::factory()->for($this->project)->todo()->highPriority()->create(['title' => 'Fix the login redirect loop']);
        Task::factory()->for($this->project)->done()->highPriority()->create(['title' => 'Fix the login banner']);
        Task::factory()->for($this->project)->todo()->lowPriority()->create(['title' => 'Fix the login copy']);
        Task::factory()->for($this->project)->todo()->highPriority()->create(['title' => 'Document the export format']);

        Sanctum::actingAs($this->user);

        $query = http_build_query([
            'status' => TaskStatus::Todo->value,
            'priority' => TaskPriority::High->value,
            'search' => 'login',
        ]);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?{$query}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Fix the login redirect loop');
    }

    public function test_it_rejects_filters_outside_the_enums(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?status=archived")
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['status']]);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?priority=urgent")
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertJsonStructure(['errors' => ['priority']]);
    }

    public function test_it_paginates_with_the_configured_default_and_clamps_oversized_requests(): void
    {
        config(['api.pagination.default' => 5, 'api.pagination.max' => 10]);

        Task::factory()->count(12)->for($this->project)->create();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonCount(5, 'data');

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?per_page=5000")
            ->assertOk()
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_it_returns_pagination_metadata_at_the_envelope_root(): void
    {
        Task::factory()->count(12)->for($this->project)->create();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$this->project->id}/tasks?per_page=5&page=2")
            ->assertOk()
            ->assertJsonPath('meta.current_page', 2)
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

    public function test_it_exposes_exactly_the_intended_task_fields(): void
    {
        Task::factory()->for($this->project)->create();

        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks")->assertOk();

        $this->assertSame(
            ['id', 'project_id', 'title', 'description', 'status', 'priority', 'due_date', 'completed_at', 'created_at', 'updated_at'],
            array_keys((array) $response->json('data.0')),
        );
    }

    public function test_listing_is_allowed_for_archived_and_completed_projects(): void
    {
        $archived = Project::factory()->for($this->user)->archived()->create();
        $completed = Project::factory()->for($this->user)->completed()->create();

        Task::factory()->count(2)->for($archived)->create();
        Task::factory()->count(3)->for($completed)->create();

        Sanctum::actingAs($this->user);

        $this->getJson("/api/v1/projects/{$archived->id}/tasks")->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson("/api/v1/projects/{$completed->id}/tasks")->assertOk()->assertJsonPath('meta.total', 3);
    }
}
