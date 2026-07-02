<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('qr_code', 40)->nullable()->unique()->after('pdf_filename');
        });

        $usedCodes = [];
        DB::table('employees')->whereNull('qr_code')->orderBy('id')->select('id')->lazy()
            ->each(function ($row) use (&$usedCodes) {
                do {
                    $code = Str::random(32);
                } while (in_array($code, $usedCodes, true));
                $usedCodes[] = $code;

                DB::table('employees')->where('id', $row->id)->update(['qr_code' => $code]);
            });

        DB::statement('ALTER TABLE employees ALTER COLUMN qr_code SET NOT NULL');
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('qr_code');
        });
    }
};
