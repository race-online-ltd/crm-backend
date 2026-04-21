<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feature_action_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_view_id');
            $table->boolean('read')->nullable();
            $table->boolean('write')->nullable();
            $table->boolean('modify')->nullable();
            $table->boolean('delete')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feature_action_permissions');
    }
};
