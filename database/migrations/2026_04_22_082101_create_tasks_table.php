<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::create('tasks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('lead_id')->nullable()->constrained('leads')->nullOnDelete();
        $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
        $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->string('assignment_mode')->nullable();
        $table->foreignId('task_type_id')->constrained('task_types')->restrictOnDelete();
        $table->string('title');
        $table->text('details')->nullable();
        $table->dateTime('scheduled_at');
        $table->text('location_address')->nullable();
        $table->decimal('location_latitude', 10, 7)->nullable();
        $table->decimal('location_longitude', 10, 7)->nullable();
        $table->boolean('reminder_enabled')->default(false);
        $table->unsignedInteger('reminder_offset_minutes')->nullable();
        $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
        $table->softDeletes();
    });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};