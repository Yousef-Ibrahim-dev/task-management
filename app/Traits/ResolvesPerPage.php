<?php

declare(strict_types=1);

namespace App\Traits;

trait ResolvesPerPage
{
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
