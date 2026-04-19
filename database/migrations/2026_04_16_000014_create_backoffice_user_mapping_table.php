<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backoffice_user_mapping', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backoffice_id')->constrained('backoffice')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['backoffice_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backoffice_user_mapping');
    }
};
