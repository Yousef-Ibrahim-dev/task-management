<?php

declare(strict_types=1);

namespace App\Interfaces\Repositories;

use App\Models\User;

interface UserRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    /**
     * The password must already be hashed by the caller: hashing is part of the
     * authentication workflow, not of generic persistence.
     *
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data): User;
}
