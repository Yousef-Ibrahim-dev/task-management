<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Entry Point
|--------------------------------------------------------------------------
|
| Only version groups live here. Each version keeps its own route file so a
| future v2 can be introduced without touching the routes of v1.
|
| The "api" limiter is defined in AppServiceProvider; the rate itself comes
| from config/api.php.
|
*/

Route::prefix('v1')
    ->as('api.v1.')
    ->middleware('throttle:api')
    ->group(base_path('routes/api/v1.php'));
