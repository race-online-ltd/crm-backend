<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('role_permissions')) {
            Schema::drop('role_permissions');
        }

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('role_id');
            $table->unsignedBigInteger('navigation_permission_id');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();
            $table->foreign('navigation_permission_id')
                ->references('id')
                ->on('navigation_permissions')
                ->cascadeOnDelete();

            $table->unique(
                ['role_id', 'navigation_permission_id'],
                'role_permissions_role_nav_perm_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
