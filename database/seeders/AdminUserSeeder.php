<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (empty($email) || empty($password)) {
            $this->command->error(
                'AdminUserSeeder skipped: ADMIN_PASSWORD must be at least 12 characters.'
            );
            return;
        }

        if (strlen($password) < 12) {
            $this->command->error(
                'AdminUserSeeder skipped: ADMIN_PASSWORD must be at least 12 characters.'
            );
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Yuna Vogue Admin'),
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_verified' => true,
                'email_verified_at' => now(),
            ]
        );

        if ($admin->wasRecentlyCreated) {
            $this->command->info("Admin user created: {$email}");

        }else {
            $this->command->info("Admin user already exists: {$email} (no changes made}");
        }
    }
}
