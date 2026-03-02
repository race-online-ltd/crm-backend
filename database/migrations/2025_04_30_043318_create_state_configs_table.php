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
        Schema::create('state_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sensor_id');
            $table->string('path')->nullable();
            $table->integer('value');
            $table->string('name', 255);
            $table->string('attache_sound', 255)->nullable();
            $table->string('url', 255)->nullable();
            $table->string('color', 50)->nullable();
            $table->boolean('sound')->nullable();
            $table->boolean('blink')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('state_configs');
    }
};
