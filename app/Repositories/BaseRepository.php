<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Interfaces\Repositories\BaseRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Persistence primitives shared by every repository.
 *
 * Domain specific reads (pagination, filters, ownership scopes, eager loading)
 * belong on the domain repository interface, not here, so each contract states
 * exactly which queries it supports.
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected readonly Model $model) {}

    public function findById(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $model, array $data): Model
    {
        $model->update($data);

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    /**
     * Protected on purpose: a builder returned to a service would let database
     * logic leak out of the repository layer.
     *
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Clamps a client supplied page size to the bounds in config/api.php so no
     * repository has to repeat the limits.
     */
    protected function resolvePerPage(?int $perPage = null): int
    {
        $default = (int) config('api.pagination.default');
        $max = (int) config('api.pagination.max');

        return max(1, min($perPage ?? $default, $max));
    }
}
