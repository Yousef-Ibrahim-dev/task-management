<?php

declare(strict_types=1);

namespace App\Providers;

use App\Interfaces\Repositories\DashboardRepositoryInterface;
use App\Interfaces\Repositories\ProjectRepositoryInterface;
use App\Interfaces\Repositories\TaskRepositoryInterface;
use App\Interfaces\Repositories\UserRepositoryInterface;
use App\Repositories\DashboardRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\TaskRepository;
use App\Repositories\UserRepository;
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
    public array $bindings = [
        DashboardRepositoryInterface::class => DashboardRepository::class,
        ProjectRepositoryInterface::class => ProjectRepository::class,
        TaskRepositoryInterface::class => TaskRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
    ];
}
