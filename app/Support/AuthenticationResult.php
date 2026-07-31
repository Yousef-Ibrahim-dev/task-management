<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final readonly class AuthenticationResult
{
    public function __construct(
        public User $user,
        public string $token,
    ) {}
}
