<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * Single source of truth for the API response envelope. Controllers and the
 * exception renderer both build responses through here, so the contract is
 * defined in one place.
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = '', int $status = Response::HTTP_OK): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    protected function created(mixed $data = null, string $message = ''): JsonResponse
    {
        return $this->success($data, $message, Response::HTTP_CREATED);
    }

    /**
     * Pagination metadata sits beside "data" rather than inside it, so a client
     * reads the payload the same way for paginated and non-paginated endpoints.
     */
    protected function paginated(ResourceCollection $collection, string $message = ''): JsonResponse
    {
        $paginator = $collection->resource;

        if (! $paginator instanceof LengthAwarePaginator) {
            throw new InvalidArgumentException('paginated() expects a resource collection built from a length aware paginator.');
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $collection->collection,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    protected function noContent(): Response
    {
        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $errors
     */
    protected function error(string $message, int $status = Response::HTTP_BAD_REQUEST, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => (object) $errors,
        ], $status);
    }
}
