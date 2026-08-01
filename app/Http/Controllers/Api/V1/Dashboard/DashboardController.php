<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\BaseApiController;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends BaseApiController
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        $summary = $this->dashboard->summaryForUser($this->authenticatedUserId($request));

        return $this->success(new DashboardResource($summary), 'Dashboard retrieved successfully.');
    }
}
