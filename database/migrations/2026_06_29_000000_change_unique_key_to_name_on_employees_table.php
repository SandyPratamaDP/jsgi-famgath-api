<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            // Drop old unique constraint on nik — name is now the dedup key
            $table->dropUnique(['nik']);

            // Add unique constraint on name
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->unique('nik');
        });
    }
};
