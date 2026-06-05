<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = (string) env('ADMIN_EMAIL', 'admin@demo.com');
        $adminUsername = (string) env('ADMIN_USERNAME', 'administrator');

        $this->call([
            ContractsSeeder::class,
            EmployeesSeeder::class,

            AdminUserSeeder::class,

            CenterSeeder::class,
            DepartmentSeeder::class,
            PositionSeeder::class,
            TimelineSeeder::class,
        ]);

        if (file_exists('database/seeders/SettingsSeeder.php')) {
            $this->call([
                SettingsSeeder::class,
            ]);
        }

        $adminRole = Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);

        $admin = User::query()
            ->where('email', $adminEmail)
            ->orWhere('username', $adminUsername)
            ->first();

        if ($admin && ! $admin->hasRole('Admin')) {
            $admin->assignRole($adminRole);
        }
    }
}
