<?php

namespace Database\Seeders;

use App\Models\NavigationItem;
use Illuminate\Database\Seeder;

class NavigationItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'key' => 'social',
                'label' => 'Social',
                'route' => '/social',
                'type' => 'item',
                'icon' => 'chat_bubble_outline',
                'sort_order' => 10,
                'is_active' => true,
                'children' => [],
            ],
            [
                'key' => 'performance',
                'label' => 'Performance',
                'route' => '/performance',
                'type' => 'item',
                'icon' => 'insights',
                'sort_order' => 20,
                'is_active' => true,
                'children' => [],
            ],
            [
                'key' => 'target',
                'label' => 'Target',
                'route' => '/target',
                'type' => 'item',
                'icon' => 'track_changes',
                'sort_order' => 30,
                'is_active' => true,
                'children' => [],
            ],
            [
                'key' => 'leads',
                'label' => 'Leads',
                'route' => '/leads',
                'type' => 'item',
                'icon' => 'leaderboard',
                'sort_order' => 40,
                'is_active' => true,
                'children' => [],
            ],
            [
                'key' => 'tasks',
                'label' => 'Tasks',
                'route' => '/tasks',
                'type' => 'item',
                'icon' => 'task_alt',
                'sort_order' => 50,
                'is_active' => true,
                'children' => [],
            ],
            [
                'key' => 'price_proposal',
                'label' => 'Price Proposal',
                'route' => '/price-proposal',
                'type' => 'item',
                'icon' => 'request_quote',
                'sort_order' => 60,
                'is_active' => true,
                'children' => [],
            ],
            [
                'key' => 'price_history',
                'label' => 'Price History',
                'route' => '/price-history',
                'type' => 'item',
                'icon' => 'history',
                'sort_order' => 70,
                'is_active' => true,
                'children' => [],
            ],
            [
                'key' => 'approval_requests',
                'label' => 'Approval Requests',
                'route' => '/approval-requests',
                'type' => 'item',
                'icon' => 'fact_check',
                'sort_order' => 80,
                'is_active' => true,
                'children' => [],
            ],
            [
                'key' => 'settings',
                'label' => 'Settings',
                'route' => null,
                'type' => 'group',
                'icon' => 'settings',
                'sort_order' => 90,
                'is_active' => true,
                'children' => [
                    [
                        'key' => 'settings.system_users',
                        'label' => 'System Users',
                        'route' => '/settings/system-users',
                        'type' => 'item',
                        'icon' => 'group',
                        'sort_order' => 10,
                        'is_active' => true,
                    ],
                    [
                        'key' => 'settings.access_control',
                        'label' => 'Access Control',
                        'route' => '/settings/access-control',
                        'type' => 'item',
                        'icon' => 'lock',
                        'sort_order' => 20,
                        'is_active' => true,
                    ],
                    [
                        'key' => 'settings.role_mapping',
                        'label' => 'Role Mapping',
                        'route' => '/settings/role-mapping',
                        'type' => 'item',
                        'icon' => 'admin_panel_settings',
                        'sort_order' => 30,
                        'is_active' => true,
                    ],
                    [
                        'key' => 'settings.social_settings',
                        'label' => 'Social Settings',
                        'route' => '/settings/social-settings',
                        'type' => 'item',
                        'icon' => 'hub',
                        'sort_order' => 40,
                        'is_active' => true,
                    ],
                    [
                        'key' => 'settings.business_entity',
                        'label' => 'Business Entity',
                        'route' => '/settings/business-entities',
                        'type' => 'item',
                        'icon' => 'business',
                        'sort_order' => 50,
                        'is_active' => true,
                    ],
                    [
                        'key' => 'settings.team',
                        'label' => 'Team',
                        'route' => '/settings/teams',
                        'type' => 'item',
                        'icon' => 'groups',
                        'sort_order' => 60,
                        'is_active' => true,
                    ],
                    [
                        'key' => 'settings.group',
                        'label' => 'Group',
                        'route' => '/settings/groups',
                        'type' => 'item',
                        'icon' => 'share',
                        'sort_order' => 70,
                        'is_active' => true,
                    ],
                ],
            ],
        ];

        foreach ($items as $item) {
            $children = $item['children'];
            unset($item['children']);

            $parent = NavigationItem::updateOrCreate(
                ['key' => $item['key']],
                array_merge($item, ['parent_id' => null])
            );

            foreach ($children as $child) {
                NavigationItem::updateOrCreate(
                    ['key' => $child['key']],
                    array_merge($child, ['parent_id' => $parent->id])
                );
            }
        }
    }
}
