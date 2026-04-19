<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_entity_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('business_entity_id');
            $table->unsignedBigInteger('kam_id');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('business_entity_id')->references('id')->on('business_entities')->cascadeOnDelete();
            $table->foreign('kam_id')->references('id')->on('users')->cascadeOnDelete();

            $table->unique(['user_id', 'business_entity_id', 'kam_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_entity_user_mappings');
    }
};
