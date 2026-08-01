<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\BaseApiController;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthenticationResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController extends BaseApiController
{
    public function __construct(private readonly AuthService $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->auth->register($request->payload(), $request->deviceName());

        return $this->created(new AuthenticationResource($result), 'Registered successfully.');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login($request->email(), $request->password(), $request->deviceName());

        return $this->success(new AuthenticationResource($result), 'Logged in successfully.');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout($this->authenticatedUser($request)->currentAccessToken());

        return $this->noContent();
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($this->authenticatedUser($request)), 'User retrieved successfully.');
    }
}
