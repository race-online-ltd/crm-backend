<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('navigation_permissions')) {
            Schema::create('navigation_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('navigation_item_id')
                    ->constrained('navigation_items')
                    ->cascadeOnDelete();
                $table->foreignId('permission_action_id')
                    ->constrained('permission_actions')
                    ->cascadeOnDelete();

                $table->unique(
                    ['navigation_item_id', 'permission_action_id'],
                    'nav_permissions_item_action_unique'
                );
            });

            return;
        }

        Schema::table('navigation_permissions', function (Blueprint $table) {
            $table->unique(
                ['navigation_item_id', 'permission_action_id'],
                'nav_permissions_item_action_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_permissions');
    }
};
