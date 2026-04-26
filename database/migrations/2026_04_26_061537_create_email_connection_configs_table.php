<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_connection_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->unique()->constrained('channel_connections')->cascadeOnDelete();
            $table->string('provider_label');          // e.g. Google Workspace / Outlook
            $table->string('email_address');
            $table->string('imap_host');               // e.g. imap.gmail.com
            $table->unsignedSmallInteger('imap_port'); // e.g. 993
            $table->string('imap_username');
            $table->text('imap_password');             // encrypted at rest
            $table->enum('encryption', ['ssl_tls', 'starttls', 'none'])->default('ssl_tls');
            $table->string('mailbox')->default('INBOX');
            $table->unsignedSmallInteger('polling_interval_seconds')->default(60);
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_connection_configs');
    }
};