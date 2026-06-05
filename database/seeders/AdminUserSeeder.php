<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminName = (string) env('ADMIN_NAME', 'Administrator');
        $adminEmail = (string) env('ADMIN_EMAIL', 'admin@demo.com');
        $adminUsername = (string) env('ADMIN_USERNAME', 'administrator');
        $adminPassword = (string) env('ADMIN_PASSWORD', 'admin');

        $admin = User::query()
            ->where('email', $adminEmail)
            ->orWhere('username', $adminUsername)
            ->first();

        if (! $admin) {
            User::create([
                'name' => $adminName,
                'employee_id' => null,
                'username' => $adminUsername,
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'profile_photo_path' => 'profile-photos/.default-photo.jpg',
            ]);
        }
    }
}
