<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Accounts\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Only seed test admin in local or testing environments
        if (app()->environment(['local', 'testing'])) {
            $this->call(SuperadminSeeder::class);

            $superadminEmail = (string) config('auth.superadmin.email', 'superadmin@getnow.test');
            User::where('email', $superadminEmail)->update(['is_platform_admin' => true]);

            $admin = User::where('email', 'admin@example.com')->first();
            if ($admin === null) {
                User::create([
                    'public_id' => (string) Str::ulid(),
                    'name' => 'Admin User',
                    'email' => 'admin@example.com',
                    'password' => bcrypt('password'),
                    'is_platform_admin' => true,
                    'role' => UserRole::Superadmin,
                ]);
            } else {
                $admin->update([
                    'name' => 'Admin User',
                    'password' => bcrypt('password'),
                    'is_platform_admin' => true,
                    'role' => UserRole::Superadmin,
                ]);
            }

            $this->call(ContentSeeder::class);
        }
    }
}
