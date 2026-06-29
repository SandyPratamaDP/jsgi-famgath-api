<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 20)->unique()->index();
            $table->string('name', 150);
            $table->string('department', 100);
            $table->enum('employee_type', ['local', 'expat']);
            $table->integer('total_vehicles')->default(0);
            $table->integer('total_passengers')->default(1);
            $table->enum('transport_type', ['private_car', 'bus'])->default('bus');
            $table->integer('bus_number')->nullable();
            $table->boolean('is_pic_bus')->default(false);
            $table->integer('total_bus_passengers')->nullable();
            $table->enum('attendance_status', ['absent', 'present'])->default('absent');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
