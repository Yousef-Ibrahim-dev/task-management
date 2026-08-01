<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Project;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\Project\IndexProjectRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ProjectController extends BaseApiController
{
    public function __construct(private readonly ProjectService $projects) {}

    public function index(IndexProjectRequest $request): JsonResponse
    {
        $projects = $this->projects->paginateForUser(
            $this->authenticatedUserId($request),
            $request->perPage(),
            $request->status(),
        );

        return $this->paginated(
            ProjectResource::collection($projects),
            'Projects retrieved successfully.',
        );
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = $this->projects->createForUser($this->authenticatedUserId($request), $request->payload());

        return $this->created(new ProjectResource($project), 'Project created successfully.');
    }

    public function show(Request $request, int $project): JsonResponse
    {
        $owned = $this->projects->findForUser($project, $this->authenticatedUserId($request));

        $this->authorize('view', $owned);

        return $this->success(new ProjectResource($owned), 'Project retrieved successfully.');
    }

    public function update(UpdateProjectRequest $request, int $project): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);

        $this->authorize('update', $this->projects->findForUser($project, $userId));

        $updated = $this->projects->updateForUser($project, $userId, $request->payload());

        return $this->success(new ProjectResource($updated), 'Project updated successfully.');
    }

    public function destroy(Request $request, int $project): Response
    {
        $userId = $this->authenticatedUserId($request);

        $this->authorize('delete', $this->projects->findForUser($project, $userId));

        $this->projects->deleteForUser($project, $userId);

        return $this->noContent();
    }

    public function archive(Request $request, int $project): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);

        $this->authorize('archive', $this->projects->findForUser($project, $userId));

        $archived = $this->projects->archiveForUser($project, $userId);

        return $this->success(new ProjectResource($archived), 'Project archived successfully.');
    }

    public function restoreStatus(Request $request, int $project): JsonResponse
    {
        $userId = $this->authenticatedUserId($request);

        $this->authorize('restoreStatus', $this->projects->findForUser($project, $userId));

        $restored = $this->projects->restoreForUser($project, $userId);

        return $this->success(new ProjectResource($restored), 'Project restored successfully.');
    }
}
