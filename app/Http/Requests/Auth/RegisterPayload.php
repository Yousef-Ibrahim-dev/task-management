<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

trait RegisterPayload
{
    /**
     * @return array{name: string, email: string, password: string}
     */
    public function payload(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
        ];
    }
}
