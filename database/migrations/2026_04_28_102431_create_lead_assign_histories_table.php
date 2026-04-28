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
        Schema::create('lead_assign_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')
                ->constrained('leads')
                ->cascadeOnDelete();

            // 🔗 Business entity (important for filtering/reporting)
            $table->foreignId('business_entity_id')
                ->constrained('business_entities')
                ->cascadeOnDelete();

            // 👤 From (who assigned)
            $table->unsignedTinyInteger('from_type');
            // 1 = KAM, 2 = BACKOFFICE, 3 = HELPDESK

            $table->unsignedBigInteger('from_id')->nullable();

            // 👤 To (who received)
            $table->unsignedTinyInteger('to_type');
            // 1 = KAM, 2 = BACKOFFICE, 3 = HELPDESK

            $table->unsignedBigInteger('to_id')->nullable();

            // 📝 Optional note
            $table->text('note')->nullable();

            $table->timestamps();

            // =========================
            // 🚀 INDEXES (VERY IMPORTANT)
            // =========================

            // fast lookup per lead timeline
            $table->index(['lead_id', 'created_at']);

            // business filtering (dashboard রিপোর্ট)
            $table->index(['business_entity_id', 'created_at']);

            // forward tracking (KAM → others)
            $table->index(['from_type', 'from_id', 'created_at']);

            // received কাজ (who got assigned)
            $table->index(['to_type', 'to_id', 'created_at']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_assign_histories');
    }
};
