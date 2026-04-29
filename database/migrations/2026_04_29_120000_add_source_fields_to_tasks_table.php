<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('source_id')->nullable()->after('client_id')->constrained('sources')->nullOnDelete();
            $table->text('source_info')->nullable()->after('source_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_id');
            $table->dropColumn('source_info');
        });
    }
};
