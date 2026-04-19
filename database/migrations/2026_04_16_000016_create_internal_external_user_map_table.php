<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_external_user_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('external_user_id');
            $table->foreignId('external_system_id')->constrained('external_system')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['user_id', 'external_system_id', 'external_user_id'],
                'internal_external_user_map_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_external_user_map');
    }
};
