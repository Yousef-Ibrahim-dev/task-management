<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\Task\IndexTaskRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\User;
use App\Services\TaskService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TaskController extends BaseApiController
{
    public function __construct(private readonly TaskService $tasks) {}

    public function index(IndexTaskRequest $request, int $project): JsonResponse
    {
        $tasks = $this->tasks->paginateForProject(
            $project,
            $this->userId($request),
            $request->perPage(),
            $request->status(),
            $request->priority(),
            $request->search(),
        );

        return $this->paginated(TaskResource::collection($tasks), 'Tasks retrieved successfully.');
    }

    public function store(StoreTaskRequest $request, int $project): JsonResponse
    {
        $task = $this->tasks->createForProject($project, $this->userId($request), $request->payload());

        return $this->created(new TaskResource($task), 'Task created successfully.');
    }

    public function show(Request $request, int $project, int $task): JsonResponse
    {
        $owned = $this->tasks->findForUser($task, $project, $this->userId($request));

        $this->authorize('view', $owned);

        return $this->success(new TaskResource($owned), 'Task retrieved successfully.');
    }

    public function update(UpdateTaskRequest $request, int $project, int $task): JsonResponse
    {
        $userId = $this->userId($request);

        $this->authorize('update', $this->tasks->findForUser($task, $project, $userId));

        $updated = $this->tasks->updateForUser($task, $project, $userId, $request->payload());

        return $this->success(new TaskResource($updated), 'Task updated successfully.');
    }

    public function destroy(Request $request, int $project, int $task): Response
    {
        $userId = $this->userId($request);

        $this->authorize('delete', $this->tasks->findForUser($task, $project, $userId));

        $this->tasks->deleteForUser($task, $project, $userId);

        return $this->noContent();
    }

    /**
     * The group is behind auth:sanctum, so the guard below only exists to keep
     * the identifier typed rather than nullable.
     *
     * @throws AuthenticationException
     */
    private function userId(Request $request): int
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user->id;
    }
}
