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
        Schema::create('entity_column_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('page_id')->nullable();
            $table->string('table_name')->nullable();
            $table->unsignedBigInteger('table_id')->nullable();
            $table->string('column_name')->nullable();
            $table->unsignedBigInteger('column_id')->nullable();
            $table->timestamps();

            // Optional indexes (recommended)
            $table->index('entity_id');
            $table->index('page_id');
            $table->index('table_id');
            $table->index('column_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entity_column_mappings');
    }
};
