<?php

use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('import-employees', [EmployeeController::class, 'import']);
    Route::get('employees', [EmployeeController::class, 'index']);
    Route::get('employees/search', [EmployeeController::class, 'search']);
    Route::patch('employees/{employee}', [EmployeeController::class, 'update']);
    Route::post('employees/bulk-pdf', [EmployeeController::class, 'downloadBulkPdfs']);
});
