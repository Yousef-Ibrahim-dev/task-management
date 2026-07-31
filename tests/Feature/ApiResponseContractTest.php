<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Traits\ApiResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiResponseContractTest extends TestCase
{
    use ApiResponse;

    public function test_a_successful_response_wraps_the_payload_in_the_envelope(): void
    {
        Route::get('/api/v1/testing-success', fn () => $this->success(['id' => 1], 'Resource retrieved successfully.'));

        $this->getJson('/api/v1/testing-success')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Resource retrieved successfully.',
                'data' => ['id' => 1],
            ]);
    }

    public function test_a_created_response_uses_status_201(): void
    {
        Route::post('/api/v1/testing-created', fn () => $this->created(['id' => 1], 'Resource created successfully.'));

        $this->postJson('/api/v1/testing-created')
            ->assertStatus(Response::HTTP_CREATED)
            ->assertExactJson([
                'success' => true,
                'message' => 'Resource created successfully.',
                'data' => ['id' => 1],
            ]);
    }

    public function test_a_no_content_response_has_an_empty_body(): void
    {
        Route::delete('/api/v1/testing-no-content', fn () => $this->noContent());

        $response = $this->deleteJson('/api/v1/testing-no-content');

        $response->assertStatus(Response::HTTP_NO_CONTENT);
        $this->assertSame('', $response->getContent());
    }

    public function test_a_paginated_response_keeps_meta_and_links_beside_the_data(): void
    {
        Route::get('/api/v1/testing-paginated', function () {
            $paginator = new LengthAwarePaginator(
                items: [['id' => 1], ['id' => 2]],
                total: 52,
                perPage: 15,
                currentPage: 1,
                options: ['path' => 'http://localhost/api/v1/testing-paginated'],
            );

            return $this->paginated(JsonResource::collection($paginator), 'Resources retrieved successfully.');
        });

        $this->getJson('/api/v1/testing-paginated')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'message' => 'Resources retrieved successfully.',
                'data' => [['id' => 1], ['id' => 2]],
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 15,
                    'last_page' => 4,
                    'total' => 52,
                ],
                'links' => [
                    'first' => 'http://localhost/api/v1/testing-paginated?page=1',
                    'last' => 'http://localhost/api/v1/testing-paginated?page=4',
                    'prev' => null,
                    'next' => 'http://localhost/api/v1/testing-paginated?page=2',
                ],
            ]);
    }
}
