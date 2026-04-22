<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_pipeline_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_entity_id')
                ->constrained('business_entities')
                ->restrictOnDelete();
            $table->string('stage_name');
            $table->string('color', 7);
            $table->unsignedInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['business_entity_id', 'sort_order']);
            $table->index(['business_entity_id', 'stage_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_pipeline_stages');
    }
};
