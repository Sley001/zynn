<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('store.admin.email');
        $password = config('store.admin.password');

        if (! $email || ! $password) {
            $this->command?->warn('Admin user skipped: set ADMIN_EMAIL and ADMIN_PASSWORD in .env.');

            return;
        }

        User::query()->updateOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => config('store.admin.name', 'Store Admin'),
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
