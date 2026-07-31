<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Support\AuthenticationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticationResource extends JsonResource
{
    private const TOKEN_TYPE = 'Bearer';

    public function __construct(private readonly AuthenticationResult $result)
    {
        parent::__construct($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user' => new UserResource($this->result->user),
            'token' => $this->result->token,
            'token_type' => self::TOKEN_TYPE,
            'expires_in' => $this->expiresIn(),
        ];
    }

    /**
     * Sanctum stores its lifetime in minutes; null means tokens never expire.
     */
    private function expiresIn(): ?int
    {
        $minutes = (int) config('sanctum.expiration');

        return $minutes > 0 ? $minutes * 60 : null;
    }
}
