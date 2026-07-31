<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Routes are registered per test rather than in routes/api/v1.php so the
 * foundation can be exercised without shipping endpoints that do not exist yet.
 */
class ApiExceptionRenderingTest extends TestCase
{
    public function test_unknown_api_routes_return_the_unified_json_404(): void
    {
        $this->getJson('/api/v1/non-existing-route')
            ->assertNotFound()
            ->assertExactJson([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => [],
            ]);
    }

    public function test_unauthenticated_requests_return_the_unified_json_401(): void
    {
        Route::middleware('auth:sanctum')->get('/api/v1/testing-protected', fn () => response()->noContent());

        $this->getJson('/api/v1/testing-protected')
            ->assertUnauthorized()
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => [],
            ]);
    }

    public function test_validation_failures_return_the_unified_json_422(): void
    {
        Route::post('/api/v1/testing-validation', function (Request $request) {
            $request->validate(['title' => ['required', 'string']]);

            return response()->noContent();
        });

        $this->postJson('/api/v1/testing-validation', [])
            ->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY)
            ->assertExactJson([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => [
                    'title' => ['The title field is required.'],
                ],
            ]);
    }

    public function test_an_unsupported_http_method_returns_the_unified_json_405(): void
    {
        Route::get('/api/v1/testing-method', fn () => response()->noContent());

        $this->postJson('/api/v1/testing-method')
            ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED)
            ->assertExactJson([
                'success' => false,
                'message' => 'The HTTP method is not supported for this endpoint.',
                'errors' => [],
            ]);
    }

    public function test_an_unexpected_failure_leaks_nothing_once_debug_is_off(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/v1/testing-failure', function () {
            throw new RuntimeException('SQLSTATE[HY000] access denied for user sail at /var/www/html/config/database.php');
        });

        $this->getJson('/api/v1/testing-failure')
            ->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR)
            ->assertExactJson([
                'success' => false,
                'message' => 'An unexpected error occurred.',
                'errors' => [],
            ]);
    }

    public function test_exceeding_the_rate_limit_returns_the_unified_json_429(): void
    {
        config(['api.rate_limit.per_minute' => 1]);

        Route::middleware('throttle:api')->get('/api/v1/testing-throttle', fn () => response()->noContent());

        $this->getJson('/api/v1/testing-throttle')->assertNoContent();

        $this->getJson('/api/v1/testing-throttle')
            ->assertStatus(Response::HTTP_TOO_MANY_REQUESTS)
            ->assertExactJson([
                'success' => false,
                'message' => 'Too many requests. Please retry later.',
                'errors' => [],
            ]);
    }
}
