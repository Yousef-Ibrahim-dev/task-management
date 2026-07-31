<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Repository contract => implementation.
     *
     * Declared as bindings rather than in register() so adding a repository is
     * a single line, and so services can type-hint the interface only.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [];
}
