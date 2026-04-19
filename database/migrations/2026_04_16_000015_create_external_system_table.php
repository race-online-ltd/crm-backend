<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_system', function (Blueprint $table) {
            $table->id();
            $table->string('external_system_name')->unique();
            $table->string('external_system_api');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_system');
    }
};
