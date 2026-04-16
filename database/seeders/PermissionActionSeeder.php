<?php

namespace Database\Seeders;

use App\Models\PermissionAction;
use Illuminate\Database\Seeder;

class PermissionActionSeeder extends Seeder
{
    public function run(): void
    {
        $actions = [
            ['key' => 'view', 'label' => 'View'],
            ['key' => 'create', 'label' => 'Create'],
            ['key' => 'update', 'label' => 'Update'],
            ['key' => 'delete', 'label' => 'Delete'],
            ['key' => 'approve', 'label' => 'Approve'],
        ];

        foreach ($actions as $action) {
            PermissionAction::updateOrCreate(
                ['key' => $action['key']],
                ['label' => $action['label']]
            );
        }
    }
}
