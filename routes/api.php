<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
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

    // Panitia only
    Route::middleware('role:panitia')->group(function () {
        Route::post('import-employees',        [EmployeeController::class, 'import']);
        Route::post('employees/generate-pdfs', [EmployeeController::class, 'generateAndDownloadPdfs']);
        Route::get('employees/{employee}/pdf', [EmployeeController::class, 'downloadSinglePdf']);
    });
});
