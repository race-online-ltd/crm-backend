<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AppSetup extends Command
{
    protected $signature = 'app:setup {--fresh}';
    protected $description = 'Initial app setup (admin + navigation + permissions)';

    public function handle()
    {


        try {

            // =========================
            // 🔥 1. Fresh Mode
            // =========================
            if ($this->option('fresh')) {

                $this->warn('Fresh setup: truncating tables...');

                DB::statement('SET FOREIGN_KEY_CHECKS=0;');

                DB::table('role_permissions')->truncate();
                DB::table('navigation_permissions')->truncate();
                DB::table('permission_actions')->truncate();
                DB::table('navigation_items')->truncate();
                DB::table('users')->truncate();
                DB::table('roles')->truncate();

                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            DB::beginTransaction();
            // =========================
            // 🔹 2. Role Create
            // =========================
            DB::table('roles')->updateOrInsert(
                ['name' => 'Admin'],
                []
            );

            $roleId = DB::table('roles')
                ->where('name', 'Admin')
                ->value('id');

            // =========================
            // 🔹 3. Admin User Create
            // =========================
            $user = User::updateOrCreate(
                ['user_name' => 'admin'],
                [
                    'full_name' => 'Admin',
                    'password' => Hash::make(env('ADMIN_PASSWORD', 'Root@@web')),
                    'role_id' => $roleId,
                    'status' => true
                ]
            );

            // =========================
            // 🔹 4. Navigation Insert (Sidebar Based) :contentReference[oaicite:0]{index=0}
            // =========================
            $navItems = [
    ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => '/', 'icon' => 'Dashboard', 'sort' => 1],

    ['key' => 'social', 'label' => 'Social', 'route' => '/social', 'icon' => 'Forum', 'sort' => 2],
    ['key' => 'performance', 'label' => 'Performance', 'route' => '/performance', 'icon' => 'Insights', 'sort' => 3],
    ['key' => 'target', 'label' => 'Target', 'route' => '/target', 'icon' => 'TrackChanges', 'sort' => 4],
    ['key' => 'leads', 'label' => 'Leads', 'route' => '/leads', 'icon' => 'Leaderboard', 'sort' => 5],
    ['key' => 'tasks', 'label' => 'Tasks', 'route' => '/tasks', 'icon' => 'TaskAlt', 'sort' => 6],
    ['key' => 'clients', 'label' => 'Clients', 'route' => '/clients', 'icon' => 'PeopleAlt', 'sort' => 7],

    ['key' => 'price-proposal', 'label' => 'Price Proposal', 'route' => '/price-proposal', 'icon' => 'RequestQuote', 'sort' => 8],
    ['key' => 'price-history', 'label' => 'Price History', 'route' => '/price-history', 'icon' => 'History', 'sort' => 9],
    ['key' => 'approval-requests', 'label' => 'Approval Requests', 'route' => '/approval/requests', 'icon' => 'AssignmentTurnedIn', 'sort' => 10],

    // group
    ['key' => 'settings', 'label' => 'Settings', 'icon' => 'Settings', 'sort' => 11],

    // children
    ['key' => 'settings.users', 'label' => 'System Users', 'route' => '/settings/users', 'parent' => 'settings', 'sort' => 1],
    ['key' => 'settings.data-access-control', 'label' => 'Access Control', 'route' => '/settings/data-access-control', 'parent' => 'settings', 'sort' => 2],
    ['key' => 'settings.column-mapping', 'label' => 'Column Mapping', 'route' => '/settings/column-mapping', 'parent' => 'settings', 'sort' => 3],
    ['key' => 'settings.role-mapping', 'label' => 'Role Mapping', 'route' => '/settings/role-mapping', 'parent' => 'settings', 'sort' => 4],
    ['key' => 'settings.social', 'label' => 'Social Settings', 'route' => '/settings/social', 'parent' => 'settings', 'sort' => 5],
    ['key' => 'settings.backoffice', 'label' => 'Backoffice Management', 'route' => '/settings/backoffice-management', 'parent' => 'settings', 'sort' => 6],
    ['key' => 'settings.kam-mapping', 'label' => 'KAM Mapping', 'route' => '/settings/kam-mapping', 'parent' => 'settings', 'sort' => 7],
    ['key' => 'settings.business-entities', 'label' => 'Business Entity', 'route' => '/settings/business-entities', 'parent' => 'settings', 'sort' => 8],
    ['key' => 'settings.team', 'label' => 'Team', 'route' => '/settings/team', 'parent' => 'settings', 'sort' => 9],
    ['key' => 'settings.group', 'label' => 'Group', 'route' => '/settings/group', 'parent' => 'settings', 'sort' => 10],
];

        $navMap = [];

foreach ($navItems as $item) {

    // 🔹 parent resolve
    $parentId = null;
    if (!empty($item['parent'])) {
        $parentId = $navMap[$item['parent']] ?? null;
    }

    // 🔥 TYPE RULE (final)
    $type = !empty($item['route']) ? 'item' : 'group';

    DB::table('navigation_items')->updateOrInsert(
        ['key' => $item['key']],
        [
            'label' => $item['label'],
            'route' => $item['route'] ?? null,
            'icon' => $item['icon'] ?? null,
            'type' => $type,
            'parent_id' => $parentId,
            'sort_order' => $item['sort'] ?? 0,
            'is_active' => 1,
            'updated_at' => now(),
            'created_at' => now(),
        ]
    );

    // 🔹 store id
    $navMap[$item['key']] = DB::table('navigation_items')
        ->where('key', $item['key'])
        ->value('id');
}

            // =========================
            // 🔹 5. Permission Generate (create, update, view, delete)
            // =========================
            $baseActions = ['view', 'create', 'update', 'delete'];

            $navs = DB::table('navigation_items')->get();

            foreach ($navs as $nav) {
                foreach ($baseActions as $action) {

                    $key = "{$nav->key}.{$action}";

                    DB::table('permission_actions')->updateOrInsert(
                        ['key' => $key],
                        ['label' => ucfirst($action)]
                    );
                }
            }

            // =========================
            // 🔹 6. Navigation Permissions Map
            // =========================
            $actions = DB::table('permission_actions')->pluck('id', 'key');

            foreach ($navs as $nav) {
                // ❌ group skip
                if ($nav->type !== 'item') {
                    continue;
                }
                foreach ($actions as $key => $actionId) {

                    if (str_starts_with($key, $nav->key . '.')) {

                        DB::table('navigation_permissions')->updateOrInsert(
                            [
                                'navigation_item_id' => $nav->id,
                                'permission_action_id' => $actionId
                            ],
                            []
                        );
                    }
                }
            }

            // =========================
            // 🔹 7. Assign সব permission → Admin
            // =========================
            $navPermIds = DB::table('navigation_permissions')->pluck('id');

            foreach ($navPermIds as $id) {
                DB::table('role_permissions')->updateOrInsert(
                    [
                        'role_id' => $roleId,
                        'navigation_permission_id' => $id
                    ],
                    []
                );
            }

            DB::commit();

            $this->info('App setup completed successfully ✅');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error($e->getMessage());
        }
    }
}
