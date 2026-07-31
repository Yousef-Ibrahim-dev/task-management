<?php

declare(strict_types=1);

namespace App\Interfaces\Repositories;

use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    public function findById(int $id): ?Model;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $model, array $data): Model;

    public function delete(Model $model): bool;
}
