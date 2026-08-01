<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public const DEMO_EMAIL = 'demo@example.com';

    public const SECOND_EMAIL = 'second@example.com';

    public const PASSWORD = 'password';

    public function run(): void
    {
        $this->createUser('Demo User', self::DEMO_EMAIL);
        $this->createUser('Second User', self::SECOND_EMAIL);
    }

    private function createUser(string $name, string $email): void
    {
        User::query()->updateOrCreate(
            ['email' => Str::lower(trim($email))],
            ['name' => $name, 'password' => Hash::make(self::PASSWORD)],
        );
    }
}
