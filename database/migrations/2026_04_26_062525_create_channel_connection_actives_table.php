<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_connection_active', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_entity_id')->constrained('business_entities')->cascadeOnDelete();
            $table->enum('channel_key', ['facebook', 'whatsapp', 'email']);
            $table->foreignId('connection_id')->constrained('channel_connections')->cascadeOnDelete();
            $table->timestamp('set_at')->useCurrent();

            // Core constraint: only one active connection per entity+channel
            $table->unique(['business_entity_id', 'channel_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_connection_active');
    }
};