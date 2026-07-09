<?php

use App\Http\Controllers\AncolQrController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\WahanaCheckinController;
use Illuminate\Support\Facades\Route;

// ── Auth (public) ─────────────────────────────────────────────────────────────
Route::prefix('v1/auth')->group(function () {
    Route::post('login',  [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me',      [AuthController::class, 'me']);
    });
});

// ── Authenticated routes ──────────────────────────────────────────────────────
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    // EO + Panitia: employee lookup and transport switch (gate scanner)
    Route::get('employees',              [EmployeeController::class, 'index']);
    Route::get('employees/search',       [EmployeeController::class, 'search']);
    Route::patch('employees/{employee}', [EmployeeController::class, 'update']);

    // EO + Panitia: wahana (Sea World / Samudera Ancol) QR-based entry
    Route::get('wahana/search',              [WahanaCheckinController::class, 'search']);
    Route::get('wahana/{code}',              [WahanaCheckinController::class, 'lookup']);
    Route::post('wahana/{employee}/checkin', [WahanaCheckinController::class, 'checkin']);

    // EO + Panitia: Ancol gate-entry QR, one per employee category
    Route::get('ancol-qr/{category}', [AncolQrController::class, 'show'])
        ->where('category', 'local|expat|operational');

    // Panitia only
    Route::middleware('role:panitia')->group(function () {
        Route::post('import-employees',          [EmployeeController::class, 'import']);
        Route::get('tickets/blank',                     [EmployeeController::class, 'blankTicketForm']);
        Route::post('employees/blast-email',           [EmployeeController::class, 'blastEmail']);
        Route::post('employees/{employee}/send-email', [EmployeeController::class, 'sendEmail']);
        Route::get('employees/{employee}/pdf',   [EmployeeController::class, 'downloadSinglePdf']);
        Route::get('employees/{employee}/image', [EmployeeController::class, 'downloadSingleImage']);
        Route::get('employees/{employee}/qr',    [EmployeeController::class, 'downloadQrCode']);
        Route::post('ancol-qr/{category}', [AncolQrController::class, 'upload'])
            ->where('category', 'local|expat|operational');
    });
});
