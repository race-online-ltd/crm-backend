<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')
                ->constrained('tasks')
                ->cascadeOnDelete();
            $table->foreignId('created_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('content');
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });

        Schema::create('task_note_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_note_id')
                ->constrained('task_notes')
                ->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->index('task_note_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_note_attachments');
        Schema::dropIfExists('task_notes');
    }
};
