<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE employees DROP CONSTRAINT employees_transport_type_check');
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_transport_type_check CHECK (transport_type IN ('private_car', 'bus', 'operational'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE employees DROP CONSTRAINT employees_transport_type_check');
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_transport_type_check CHECK (transport_type IN ('private_car', 'bus'))");
    }
};
