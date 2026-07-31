<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Project\ProjectController;
use App\Http\Controllers\Api\V1\Task\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->as('auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:auth-register')
        ->name('register');

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:auth-login')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::patch('projects/{project}/archive', [ProjectController::class, 'archive'])
        ->whereNumber('project')
        ->name('projects.archive');

    Route::patch('projects/{project}/restore-status', [ProjectController::class, 'restoreStatus'])
        ->whereNumber('project')
        ->name('projects.restore-status');

    Route::apiResource('projects', ProjectController::class)
        ->where(['project' => '[0-9]+']);

    Route::apiResource('projects.tasks', TaskController::class)
        ->where(['project' => '[0-9]+', 'task' => '[0-9]+']);
});
