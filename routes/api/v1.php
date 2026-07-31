<?php

use App\Http\Controllers\Api\V1\Project\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])
        ->whereNumber('project')
        ->name('projects.archive');

    Route::patch('projects/{project}/restore-status', [ProjectController::class, 'restoreStatus'])
        ->whereNumber('project')
        ->name('projects.restore-status');

    Route::apiResource('projects', ProjectController::class)
        ->where(['project' => '[0-9]+']);
});
