<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_assign', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });

        DB::table('lead_assign')->insert([
            ['name' => 'KAM'],
            ['name' => 'Back Office'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assign');
    }
};
