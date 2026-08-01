<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class BaseApiController extends Controller
{
    use ApiResponse;
    use AuthorizesRequests;

    /**
     * Every API route sits behind auth:sanctum, so the guard below only exists
     * to turn a nullable contract into a typed model rather than to authenticate.
     *
     * @throws AuthenticationException
     */
    protected function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * @throws AuthenticationException
     */
    protected function authenticatedUserId(Request $request): int
    {
        return $this->authenticatedUser($request)->id;
    }
}
