<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\BaseApiController;
use App\Http\Resources\DashboardResource;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends BaseApiController
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        $summary = $this->dashboard->summaryForUser($this->userId($request));

        return $this->success(new DashboardResource($summary), 'Dashboard retrieved successfully.');
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
