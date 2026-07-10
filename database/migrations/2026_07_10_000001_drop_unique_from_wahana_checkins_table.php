<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wahana_checkins', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'wahana']);
            $table->index(['employee_id', 'wahana']);
        });
    }

    public function down(): void
    {
        Schema::table('wahana_checkins', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'wahana']);
            $table->unique(['employee_id', 'wahana']);
        });
    }
};
