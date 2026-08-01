<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public const WEBSITE_REDESIGN = 'Website Redesign';

    public const MOBILE_APP_LAUNCH = 'Mobile App Launch';

    public const INTERNAL_REPORTING = 'Internal Reporting';

    public const LEGACY_MIGRATION = 'Legacy Migration';

    public const CLIENT_PORTAL = 'Client Portal';

    public const SUPPORT_HANDBOOK = 'Support Handbook';

    public function run(): void
    {
        $demo = $this->user(UserSeeder::DEMO_EMAIL);
        $second = $this->user(UserSeeder::SECOND_EMAIL);

        $this->createProject($demo, self::WEBSITE_REDESIGN, ProjectStatus::Active, 'Rebuild the marketing site on the new design system.');
        $this->createProject($demo, self::MOBILE_APP_LAUNCH, ProjectStatus::Active, 'Ship the first public release to both app stores.');
        $this->createProject($demo, self::INTERNAL_REPORTING, ProjectStatus::Completed, 'Move reporting off the primary database.');
        $this->createProject($demo, self::LEGACY_MIGRATION, ProjectStatus::Archived, 'Retire the last of the old PHP endpoints.');

        $this->createProject($second, self::CLIENT_PORTAL, ProjectStatus::Active, 'Self-service area for retainer clients.');
        $this->createProject($second, self::SUPPORT_HANDBOOK, ProjectStatus::Archived, 'Written answers for the questions support repeats.');
    }

    private function user(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }

    private function createProject(User $user, string $name, ProjectStatus $status, string $description): void
    {
        Project::query()->updateOrCreate(
            ['user_id' => $user->id, 'name' => $name],
            ['status' => $status, 'description' => $description],
        );
    }
}
