<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('status')->default('pending')->after('assignment_mode');
            $table->timestamp('checked_in_at')->nullable()->after('status');
            $table->decimal('checked_in_latitude', 10, 7)->nullable()->after('checked_in_at');
            $table->decimal('checked_in_longitude', 10, 7)->nullable()->after('checked_in_latitude');
            $table->unsignedInteger('checked_in_distance_meters')->nullable()->after('checked_in_longitude');
            $table->timestamp('completed_at')->nullable()->after('checked_in_distance_meters');
            $table->text('completion_message')->nullable()->after('completed_at');
            $table->timestamp('cancelled_at')->nullable()->after('completion_message');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn([
                'status',
                'checked_in_at',
                'checked_in_latitude',
                'checked_in_longitude',
                'checked_in_distance_meters',
                'completed_at',
                'completion_message',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
