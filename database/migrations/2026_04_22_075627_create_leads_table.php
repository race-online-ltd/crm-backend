<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::create('leads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('business_entity_id')->constrained('business_entities')->restrictOnDelete();
    $table->foreignId('source_id')->constrained('sources')->restrictOnDelete();
    $table->foreignId('client_id')->constrained('clients')->restrictOnDelete();
    $table->foreignId('lead_pipeline_stage_id')->constrained('lead_pipeline_stages')->restrictOnDelete();
    $table->decimal('expected_revenue', 15, 2)->nullable();
    $table->date('deadline')->nullable();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
});
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};