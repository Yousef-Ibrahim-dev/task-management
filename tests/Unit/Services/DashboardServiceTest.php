<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Interfaces\Repositories\DashboardRepositoryInterface;
use App\Services\DashboardService;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    private DashboardRepositoryInterface&MockInterface $repository;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(DashboardRepositoryInterface::class);
        $this->service = new DashboardService($this->repository);
    }

    public function test_it_delegates_to_the_repository_with_the_given_user(): void
    {
        $summary = [
            'total_projects' => 9,
            'active_projects' => 2,
            'completed_projects' => 3,
            'archived_projects' => 4,
            'total_tasks' => 20,
            'completed_tasks' => 5,
            'pending_tasks' => 15,
            'overdue_tasks' => 6,
        ];

        $this->repository->shouldReceive('summary')
            ->once()
            ->with(7)
            ->andReturn($summary);

        $this->assertSame($summary, $this->service->summaryForUser(7));
    }

    public function test_it_does_not_reshape_the_repository_result(): void
    {
        $summary = [
            'total_projects' => 0,
            'active_projects' => 0,
            'completed_projects' => 0,
            'archived_projects' => 0,
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'pending_tasks' => 0,
            'overdue_tasks' => 0,
        ];

        $this->repository->shouldReceive('summary')->once()->andReturn($summary);

        $this->assertSame(array_keys($summary), array_keys($this->service->summaryForUser(1)));
    }

    public function test_it_propagates_a_repository_failure(): void
    {
        $this->repository->shouldReceive('summary')
            ->once()
            ->andThrow(new RuntimeException('database is down'));

        $this->expectException(RuntimeException::class);

        $this->service->summaryForUser(7);
    }
}
