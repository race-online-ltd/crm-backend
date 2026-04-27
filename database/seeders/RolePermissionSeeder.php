<?php

namespace Database\Seeders;

use App\Models\NavigationPermission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'Admin' => ['*'],
            'Supervisor' => [
                'social.view',
                'performance.view',
                'target.view',
                'leads.view',
                'leads.create',
                'leads.update',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'price_proposal.view',
                'price_proposal.create',
                'price_proposal.update',
                'price_proposal.approve',
                'price_history.view',
                'approval_requests.view',
                'approval_requests.approve',
                'settings.view',
                'settings.system_users.view',
                'settings.business_entity.view',
                'settings.team.view',
                'settings.group.view',
            ],
            'Helpdesk' => [
                'social.view',
                'social.update',
                'performance.view',
                'target.view',
                'leads.view',
                'tasks.view',
                'tasks.update',
                'price_proposal.view',
                'price_history.view',
                'approval_requests.view',
            ],
            'Sales Manager' => [
                'social.view',
                'performance.view',
                'target.view',
                'leads.view',
                'leads.create',
                'leads.update',
                'leads.delete',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'tasks.delete',
                'price_proposal.view',
                'price_proposal.create',
                'price_proposal.update',
                'price_history.view',
                'approval_requests.view',
            ],
            'Team Lead' => [
                'social.view',
                'performance.view',
                'target.view',
                'leads.view',
                'leads.create',
                'leads.update',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'price_proposal.view',
                'price_proposal.create',
                'price_proposal.update',
                'price_history.view',
                'approval_requests.view',
            ],
            'Key Account Manager' => [
                'social.view',
                'performance.view',
                'target.view',
                'leads.view',
                'leads.create',
                'leads.update',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'price_proposal.view',
                'price_proposal.create',
                'price_proposal.update',
                'price_history.view',
            ],
            'Sales Executive' => [
                'social.view',
                'target.view',
                'leads.view',
                'leads.create',
                'tasks.view',
                'tasks.create',
                'tasks.update',
                'price_proposal.view',
                'price_proposal.create',
                'price_history.view',
            ],
            'Approver' => [
                'price_proposal.view',
                'price_proposal.approve',
                'approval_requests.view',
                'approval_requests.approve',
                'price_history.view',
            ],
            'Viewer' => [
                'social.view',
                'performance.view',
                'target.view',
                'leads.view',
                'tasks.view',
                'price_proposal.view',
                'price_history.view',
                'approval_requests.view',
                'settings.view',
                'settings.system_users.view',
                'settings.access_control.view',
                'settings.role_mapping.view',
                'settings.social_settings.view',
                'settings.business_entity.view',
                'settings.team.view',
                'settings.group.view',
            ],
        ];

        $roles = Role::query()->get()->keyBy('name');
        $permissions = NavigationPermission::query()
            ->with(['navigationItem:id,key', 'permissionAction:id,key'])
            ->get()
            ->mapWithKeys(fn (NavigationPermission $permission) => [
                $permission->navigationItem->key . '.' . $permission->permissionAction->key => $permission,
            ]);

        RolePermission::query()->delete();

        foreach ($matrix as $roleName => $keys) {
            $role = $roles->get($roleName);

            if (! $role) {
                continue;
            }

            $assignedPermissions = $keys === ['*']
                ? $permissions
                : collect($keys)
                    ->map(fn (string $key) => $permissions->get($key))
                    ->filter();

            foreach ($assignedPermissions as $permission) {
                RolePermission::updateOrCreate([
                    'role_id' => $role->id,
                    'navigation_permission_id' => $permission->id,
                ]);
            }
        }
    }
}
