<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_pipeline_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_entity_id')
                ->constrained('business_entities')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedInteger('level');
            $table->timestamps();

            $table->index(['business_entity_id', 'level']);
            $table->index(['business_entity_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_pipeline_steps');
    }
};
