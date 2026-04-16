<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminRole = Role::updateOrCreate([
            'name' => 'Admin',
        ]);

        User::updateOrCreate(
            ['user_name' => 'admin'],
            [
                'full_name' => 'Admin',
                'email' => null,
                'phone' => null,
                'password' => 'Root@@web1',
                'role_id' => $adminRole->id,
                'status' => true,
            ]
        );

        $this->call([
            CrmRoleSeeder::class,
            PermissionActionSeeder::class,
            NavigationItemSeeder::class,
            NavigationPermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);
    }
}
