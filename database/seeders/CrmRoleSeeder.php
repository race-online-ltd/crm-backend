<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrmRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['id' => 1, 'name' => 'Admin'],
            ['id' => 2, 'name' => 'Supervisor'],
            ['id' => 3, 'name' => 'Helpdesk'],
            ['id' => 4, 'name' => 'Sales Manager'],
            ['id' => 5, 'name' => 'Team Lead'],
            ['id' => 6, 'name' => 'Key Account Manager'],
            ['id' => 7, 'name' => 'Sales Executive'],
            ['id' => 8, 'name' => 'Approver'],
            ['id' => 9, 'name' => 'Viewer'],
        ];

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('role_permissions')->truncate();
        DB::table('roles')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        DB::table('roles')->insert($roles);
    }
}
