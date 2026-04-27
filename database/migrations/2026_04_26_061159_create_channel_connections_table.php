<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_entity_id')->constrained('business_entities')->cascadeOnDelete();
            $table->enum('channel_key', ['facebook', 'whatsapp', 'email']);
            $table->string('connection_name');
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

                $table->unique(['business_entity_id', 'channel_key', 'connection_name'], 'uq_cc_entity_channel_name');
                $table->index(['business_entity_id', 'channel_key'], 'idx_cc_entity_channel');
                        });
                }

    public function down(): void
    {
        Schema::dropIfExists('channel_connections');
    }
};