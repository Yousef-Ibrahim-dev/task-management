<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Dashboard;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const COUNTERS = [
        'total_projects',
        'active_projects',
        'completed_projects',
        'archived_projects',
        'total_tasks',
        'completed_tasks',
        'pending_tasks',
        'overdue_tasks',
    ];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-01 12:00:00'));

        $this->user = User::factory()->create();
    }

    public function test_it_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard')
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_it_returns_zeroes_for_a_new_account(): void
    {
        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Dashboard retrieved successfully.')
            ->assertExactJson([
                'success' => true,
                'message' => 'Dashboard retrieved successfully.',
                'data' => array_fill_keys(self::COUNTERS, 0),
            ]);
    }

    public function test_it_returns_exactly_the_documented_counters(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/dashboard')->assertOk();

        $this->assertSame(self::COUNTERS, array_keys((array) $response->json('data')));
        $response->assertJsonMissingPath('data.created_at')
            ->assertJsonMissingPath('data.updated_at')
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_it_counts_projects_by_status(): void
    {
        Project::factory()->count(2)->for($this->user)->create();
        Project::factory()->count(3)->for($this->user)->completed()->create();
        Project::factory()->count(4)->for($this->user)->archived()->create();

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_projects', 9)
            ->assertJsonPath('data.active_projects', 2)
            ->assertJsonPath('data.completed_projects', 3)
            ->assertJsonPath('data.archived_projects', 4);
    }

    public function test_it_ignores_soft_deleted_projects(): void
    {
        Project::factory()->count(2)->for($this->user)->create();
        Project::factory()->for($this->user)->archived()->create()->delete();

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_projects', 2)
            ->assertJsonPath('data.archived_projects', 0);
    }

    public function test_it_counts_tasks_by_completion_and_overdue_state(): void
    {
        $project = Project::factory()->for($this->user)->create();

        Task::factory()->count(2)->for($project)->done()->create(['due_date' => '2026-07-01']);
        Task::factory()->count(3)->for($project)->todo()->create(['due_date' => '2026-07-25']);
        Task::factory()->for($project)->inProgress()->create(['due_date' => '2026-08-01']);
        Task::factory()->for($project)->todo()->create(['due_date' => null]);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_tasks', 7)
            ->assertJsonPath('data.completed_tasks', 2)
            ->assertJsonPath('data.pending_tasks', 5)
            ->assertJsonPath('data.overdue_tasks', 3);
    }

    public function test_it_ignores_tasks_under_a_soft_deleted_project(): void
    {
        $live = Project::factory()->for($this->user)->create();
        $trashed = Project::factory()->for($this->user)->create();

        Task::factory()->count(2)->for($live)->todo()->create(['due_date' => '2026-07-01']);
        Task::factory()->count(5)->for($trashed)->todo()->create(['due_date' => '2026-07-01']);

        $trashed->delete();

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_tasks', 2)
            ->assertJsonPath('data.pending_tasks', 2)
            ->assertJsonPath('data.overdue_tasks', 2);
    }

    public function test_it_ignores_soft_deleted_tasks(): void
    {
        $project = Project::factory()->for($this->user)->create();

        Task::factory()->count(2)->for($project)->todo()->create(['due_date' => null]);
        Task::factory()->for($project)->todo()->create(['due_date' => '2026-07-01'])->delete();

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_tasks', 2)
            ->assertJsonPath('data.overdue_tasks', 0);
    }

    public function test_it_never_reports_another_users_data(): void
    {
        $stranger = User::factory()->create();
        $strangerProject = Project::factory()->for($stranger)->completed()->create();
        Task::factory()->count(6)->for($strangerProject)->done()->create(['due_date' => '2026-07-01']);

        $project = Project::factory()->for($this->user)->create();
        Task::factory()->for($project)->todo()->create(['due_date' => '2026-07-01']);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_projects', 1)
            ->assertJsonPath('data.completed_projects', 0)
            ->assertJsonPath('data.total_tasks', 1)
            ->assertJsonPath('data.completed_tasks', 0)
            ->assertJsonPath('data.overdue_tasks', 1);

        Sanctum::actingAs($stranger);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_projects', 1)
            ->assertJsonPath('data.completed_projects', 1)
            ->assertJsonPath('data.total_tasks', 6)
            ->assertJsonPath('data.completed_tasks', 6)
            ->assertJsonPath('data.overdue_tasks', 0);
    }

    public function test_every_counter_is_an_integer(): void
    {
        $project = Project::factory()->for($this->user)->create();
        Task::factory()->for($project)->todo()->create(['due_date' => '2026-07-01']);

        Sanctum::actingAs($this->user);

        $data = (array) $this->getJson('/api/v1/dashboard')->assertOk()->json('data');

        foreach (self::COUNTERS as $counter) {
            $this->assertIsInt($data[$counter], "{$counter} should be an integer.");
        }
    }
}
