<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly User $model) {}

    public function findByEmail(string $email): ?User
    {
        return $this->query()->where('email', $email)->first();
    }

    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function create(array $data): User
    {
        return $this->query()->create($data);
    }

    /**
     * @return Builder<User>
     */
    private function query(): Builder
    {
        return $this->model->newQuery();
    }
}
