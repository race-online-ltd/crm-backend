<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kam_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_entity_id')->constrained('business_entities')->cascadeOnDelete();
            $table->foreignId('kam_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('product')->cascadeOnDelete();
            $table->enum('target_mode', ['monthly', 'quarterly']);
            $table->unsignedTinyInteger('target_value');
            $table->unsignedSmallInteger('target_year');
            $table->decimal('revenue_target', 15, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['business_entity_id', 'kam_id']);
            $table->index(['target_mode', 'target_year', 'target_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kam_targets');
    }
};
