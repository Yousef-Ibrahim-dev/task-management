<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Traits\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Maps exceptions onto the API response envelope so controllers and services
 * never need try/catch blocks for expected failures.
 *
 * Returning null hands the exception back to Laravel's own renderer.
 */
final class ApiExceptionRenderer
{
    use ApiResponse;

    private const MESSAGE_VALIDATION = 'The given data was invalid.';

    private const MESSAGE_UNAUTHENTICATED = 'Unauthenticated.';

    private const MESSAGE_FORBIDDEN = 'This action is unauthorized.';

    private const MESSAGE_NOT_FOUND = 'Resource not found.';

    private const MESSAGE_METHOD_NOT_ALLOWED = 'The HTTP method is not supported for this endpoint.';

    private const MESSAGE_TOO_MANY_REQUESTS = 'Too many requests. Please retry later.';

    private const MESSAGE_UNEXPECTED = 'An unexpected error occurred.';

    public function render(Throwable $e): ?JsonResponse
    {
        return match (true) {
            // Laravel already produced a response for this one; overwriting it would
            // discard whatever the thrower intended to send.
            $e instanceof HttpResponseException => null,
            $e instanceof ValidationException => $this->error(self::MESSAGE_VALIDATION, Response::HTTP_UNPROCESSABLE_ENTITY, $e->errors()),
            $e instanceof AuthenticationException => $this->error(self::MESSAGE_UNAUTHENTICATED, Response::HTTP_UNAUTHORIZED),
            $e instanceof AuthorizationException => $this->error(self::MESSAGE_FORBIDDEN, Response::HTTP_FORBIDDEN),
            $e instanceof ModelNotFoundException => $this->error(self::MESSAGE_NOT_FOUND, Response::HTTP_NOT_FOUND),
            $e instanceof ArchivedProjectIsReadOnlyException => $this->error($e->getMessage(), Response::HTTP_CONFLICT),
            $e instanceof HttpExceptionInterface => $this->fromHttpException($e),
            default => $this->unexpected(),
        };
    }

    /**
     * Covers the exceptions Laravel converts before rendering: access denied,
     * not found, method not allowed and throttling all arrive here carrying
     * their own status code and headers.
     */
    private function fromHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();

        $message = match ($status) {
            Response::HTTP_UNAUTHORIZED => self::MESSAGE_UNAUTHENTICATED,
            Response::HTTP_FORBIDDEN => self::MESSAGE_FORBIDDEN,
            Response::HTTP_NOT_FOUND => self::MESSAGE_NOT_FOUND,
            Response::HTTP_METHOD_NOT_ALLOWED => self::MESSAGE_METHOD_NOT_ALLOWED,
            Response::HTTP_TOO_MANY_REQUESTS => self::MESSAGE_TOO_MANY_REQUESTS,
            default => self::MESSAGE_UNEXPECTED,
        };

        return $this->error($message, $status)->withHeaders($e->getHeaders());
    }

    private function unexpected(): ?JsonResponse
    {
        // While debugging, Laravel's renderer reports the message, file and stack
        // trace, which is more use to a developer than a generic envelope.
        if ((bool) config('app.debug')) {
            return null;
        }

        return $this->error(self::MESSAGE_UNEXPECTED, Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
