<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_entity_user_mappings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('business_entity_id');
            $table->unsignedBigInteger('kam_id');
            $table->timestamps();
            
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
            
            $table->foreign('business_entity_id')
                ->references('id')
                ->on('business_entities')
                ->restrictOnDelete();
            
            $table->foreign('kam_id')
                ->references('id')
                ->on('clients')
                ->restrictOnDelete();
            
            // Use a shorter unique constraint name
            $table->unique(['user_id', 'business_entity_id', 'kam_id'], 'uq_bem_user_entity_kam');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_entity_user_mappings');
    }
};