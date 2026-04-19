<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_entity_id')
                ->constrained('business_entities')
                ->restrictOnDelete();
            $table->string('client_id')->unique();
            $table->string('client_name');
            $table->enum('client_from', ['Prism', 'MQ', 'maxim Orbit', 'maxim Race']);
            $table->string('contact_person')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('lat', 10, 8)->nullable();
            $table->decimal('long', 11, 8)->nullable();
            $table->foreignId('division_id')
                ->constrained('divisions')
                ->restrictOnDelete();
            $table->foreignId('district_id')
                ->constrained('districts')
                ->restrictOnDelete();
            $table->foreignId('thana_id')
                ->constrained('thanas')
                ->restrictOnDelete();
            $table->enum('licence', ['Active', 'Expire', 'Pending', 'None']);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['business_entity_id', 'client_name']);
            $table->index(['division_id', 'district_id', 'thana_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
