<?php

namespace Database\Seeders;

use App\Models\NavigationItem;
use App\Models\NavigationPermission;
use App\Models\PermissionAction;
use Illuminate\Database\Seeder;

class NavigationPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'social' => ['view', 'update'],
            'performance' => ['view'],
            'target' => ['view'],
            'leads' => ['view', 'create', 'update', 'delete'],
            'tasks' => ['view', 'create', 'update', 'delete'],
            'price_proposal' => ['view', 'create', 'update', 'delete', 'approve'],
            'price_history' => ['view'],
            'approval_requests' => ['view', 'approve'],
            'settings' => ['view'],
            'settings.system_users' => ['view', 'create', 'update', 'delete'],
            'settings.access_control' => ['view', 'update'],
            'settings.role_mapping' => ['view', 'update'],
            'settings.social_settings' => ['view', 'update'],
            'settings.business_entity' => ['view', 'create', 'update', 'delete'],
            'settings.team' => ['view', 'create', 'update', 'delete'],
            'settings.group' => ['view', 'create', 'update', 'delete'],
        ];

        $items = NavigationItem::query()
            ->whereIn('key', array_keys($permissions))
            ->get()
            ->keyBy('key');

        $actions = PermissionAction::query()
            ->whereIn('key', collect($permissions)->flatten()->unique()->values())
            ->get()
            ->keyBy('key');

        foreach ($permissions as $itemKey => $actionKeys) {
            $navigationItem = $items->get($itemKey);

            if (! $navigationItem) {
                continue;
            }

            foreach ($actionKeys as $actionKey) {
                $permissionAction = $actions->get($actionKey);

                if (! $permissionAction) {
                    continue;
                }

                NavigationPermission::updateOrCreate([
                    'navigation_item_id' => $navigationItem->id,
                    'permission_action_id' => $permissionAction->id,
                ]);
            }
        }
    }
}
