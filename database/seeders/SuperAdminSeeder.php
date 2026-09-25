<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('is_super_admin', true)->exists()) {
            $this->command->info('A Super Admin already exists; credentials were not changed.');

            return;
        }

        $name = config('initial-admin.name');
        $email = config('initial-admin.email');
        $password = config('initial-admin.password');

        $hasStrongPassword = is_string($password)
            && strlen($password) >= 12
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^a-zA-Z0-9]/', $password) === 1;

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL) || ! $hasStrongPassword) {
            $this->command->warn(
                'Super Admin was not created. Set a valid SUPER_ADMIN_EMAIL and a 12+ character mixed-case password with a number and symbol.',
            );

            return;
        }

        User::query()->create([
            'name' => is_string($name) && $name !== '' ? $name : 'Super Admin',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => $password,
            'is_super_admin' => true,
            'status' => UserStatus::Active,
        ]);

        $this->command->info('Super Admin created successfully.');
    }
}
