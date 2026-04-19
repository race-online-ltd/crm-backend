<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backoffice', function (Blueprint $table) {
            $table->id();
            $table->string('backoffice_name');
            $table->foreignId('business_entity_id')->constrained('business_entities')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backoffice');
    }
};
