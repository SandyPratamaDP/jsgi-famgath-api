<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['nik', 'department', 'attendance_status', 'scanned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('nik', 20)->nullable();
            $table->string('department', 100)->nullable();
            $table->enum('attendance_status', ['absent', 'present'])->default('absent');
            $table->timestamp('scanned_at')->nullable();
        });
    }
};
