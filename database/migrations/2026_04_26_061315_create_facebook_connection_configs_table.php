<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facebook_connection_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->unique()->constrained('channel_connections')->cascadeOnDelete();
            $table->string('app_id');
            $table->text('app_secret');           // encrypted at rest
            $table->string('graph_api_version');  // e.g. v19.0
            $table->string('page_id');
            $table->string('page_name');
            $table->text('webhook_verify_token'); // encrypted at rest
            $table->text('page_access_token');    // encrypted at rest
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_connection_configs');
    }
};