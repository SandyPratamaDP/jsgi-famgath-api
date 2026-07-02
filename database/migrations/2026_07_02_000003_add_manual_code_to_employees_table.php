<?php

use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('manual_code', 8)->nullable()->unique()->after('qr_code');
        });

        DB::table('employees')->whereNull('manual_code')->orderBy('id')->select('id')->lazy()
            ->each(function ($row) {
                DB::table('employees')->where('id', $row->id)->update([
                    'manual_code' => Employee::generateUniqueManualCode(),
                ]);
            });

        DB::statement('ALTER TABLE employees ALTER COLUMN manual_code SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('manual_code');
        });
    }
};
