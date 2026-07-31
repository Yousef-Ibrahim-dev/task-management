<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ProjectStatus;
use App\Interfaces\Repositories\ProjectRepositoryInterface;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class ProjectServiceTest extends TestCase
{
    private ProjectRepositoryInterface&MockInterface $repository;

    private ProjectService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(ProjectRepositoryInterface::class);
        $this->service = new ProjectService($this->repository);
    }

    public function test_it_lists_projects_through_the_repository(): void
    {
        $page = new LengthAwarePaginator([], 0, 15);

        $this->repository
            ->shouldReceive('paginateForUser')
            ->once()
            ->with(7, 25, ProjectStatus::Archived)
            ->andReturn($page);

        $this->assertSame($page, $this->service->paginateForUser(7, 25, ProjectStatus::Archived));
    }

    public function test_it_lists_projects_without_a_page_size_or_filter(): void
    {
        $page = new LengthAwarePaginator([], 0, 15);

        $this->repository
            ->shouldReceive('paginateForUser')
            ->once()
            ->with(7, null, null)
            ->andReturn($page);

        $this->assertSame($page, $this->service->paginateForUser(7));
    }

    public function test_it_finds_a_project_through_the_repository(): void
    {
        $project = $this->project();

        $this->repository
            ->shouldReceive('findOwnedByUser')
            ->once()
            ->with(3, 7)
            ->andReturn($project);

        $this->assertSame($project, $this->service->findForUser(3, 7));
    }

    public function test_it_creates_a_project_for_a_user(): void
    {
        $project = $this->project();

        $this->repository
            ->shouldReceive('createForUser')
            ->once()
            ->with(7, ['name' => 'Search Index Rewrite'])
            ->andReturn($project);

        $this->assertSame($project, $this->service->createForUser(7, ['name' => 'Search Index Rewrite']));
    }

    public function test_it_updates_a_project_it_first_resolved_by_owner(): void
    {
        $project = $this->project();
        $updated = $this->project();

        $this->repository
            ->shouldReceive('findOwnedByUser')
            ->once()
            ->with(3, 7)
            ->andReturn($project);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($project, ['name' => 'New name'])
            ->andReturn($updated);

        $this->assertSame($updated, $this->service->updateForUser(3, 7, ['name' => 'New name']));
    }

    public function test_it_deletes_a_project_it_first_resolved_by_owner(): void
    {
        $project = $this->project();

        $this->repository
            ->shouldReceive('findOwnedByUser')
            ->once()
            ->with(3, 7)
            ->andReturn($project);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($project);

        $this->service->deleteForUser(3, 7);
    }

    public function test_it_archives_an_active_project(): void
    {
        $project = $this->project(ProjectStatus::Active);
        $archived = $this->project(ProjectStatus::Archived);

        $this->repository
            ->shouldReceive('findOwnedByUser')
            ->once()
            ->with(3, 7)
            ->andReturn($project);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($project, ['status' => ProjectStatus::Archived])
            ->andReturn($archived);

        $this->assertSame($archived, $this->service->archiveForUser(3, 7));
    }

    public function test_it_does_not_write_when_archiving_an_already_archived_project(): void
    {
        $project = $this->project(ProjectStatus::Archived);

        $this->repository
            ->shouldReceive('findOwnedByUser')
            ->once()
            ->with(3, 7)
            ->andReturn($project);

        $this->repository->shouldNotReceive('update');

        $this->assertSame($project, $this->service->archiveForUser(3, 7));
    }

    public function test_it_restores_an_archived_project(): void
    {
        $project = $this->project(ProjectStatus::Archived);
        $restored = $this->project(ProjectStatus::Active);

        $this->repository
            ->shouldReceive('findOwnedByUser')
            ->once()
            ->with(3, 7)
            ->andReturn($project);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($project, ['status' => ProjectStatus::Active])
            ->andReturn($restored);

        $this->assertSame($restored, $this->service->restoreForUser(3, 7));
    }

    public function test_it_does_not_write_when_restoring_an_already_active_project(): void
    {
        $project = $this->project(ProjectStatus::Active);

        $this->repository
            ->shouldReceive('findOwnedByUser')
            ->once()
            ->with(3, 7)
            ->andReturn($project);

        $this->repository->shouldNotReceive('update');

        $this->assertSame($project, $this->service->restoreForUser(3, 7));
    }

    public function test_it_propagates_the_repository_not_found_failure(): void
    {
        $this->repository
            ->shouldReceive('findOwnedByUser')
            ->once()
            ->with(3, 7)
            ->andThrow(new ModelNotFoundException);

        $this->repository->shouldNotReceive('update');

        $this->expectException(ModelNotFoundException::class);

        $this->service->updateForUser(3, 7, ['name' => 'New name']);
    }

    private function project(ProjectStatus $status = ProjectStatus::Active): Project
    {
        return new Project(['name' => 'Billing System Migration', 'status' => $status]);
    }
}
