<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_reminder_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('channel');
            $table->timestamps();

            $table->unique(['task_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reminder_channels');
    }
};