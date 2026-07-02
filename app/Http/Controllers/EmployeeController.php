<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\EmployeeImportService;
use App\Services\EmployeePdfService;
use App\Services\EmployeeQrService;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeImportService $importService,
        private EmployeePdfService $pdfService,
        private EmployeeQrService $qrService,
    ) {}

    public function index()
    {
        $employees = Employee::orderBy('transport_type')
            ->orderByDesc('is_pic_bus')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $employees]);
    }

    public function search(Request $request)
    {
        $employees = Employee::search(trim($request->query('query', '')))->orderBy('name')->get();
        return response()->json(['data' => $employees]);
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        $count = $this->importService->import($request->file('file'));

        return response()->json(['message' => 'Import completed successfully', 'count' => $count]);
    }

    public function update(Request $request, Employee $employee)
    {
        $attributes = $request->validate([
            'total_vehicles'   => 'sometimes|integer|min:0',
            'transport_type'   => 'sometimes|in:private_car,bus',
            'total_passengers' => 'sometimes|integer|min:1',
        ]);

        if (array_key_exists('total_vehicles', $attributes) && !array_key_exists('transport_type', $attributes)) {
            $attributes['transport_type'] = $attributes['total_vehicles'] >= 1 ? 'private_car' : 'bus';
        }

        // Domain rule: mark day-of-event switches — never cleared by this endpoint.
        if (($attributes['transport_type'] ?? null) === 'private_car' && $employee->transport_type === 'bus') {
            $attributes['switched_from_bus'] = true;
        }

        $employee->update($attributes);
        return response()->json(['data' => $employee->fresh()]);
    }

    public function generateAndDownloadPdfs()
    {
        $employees = Employee::query()
            ->where(fn ($q) => $q->where('transport_type', 'private_car')->orWhere('is_pic_bus', true))
            ->orderBy('name')
            ->get();

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'Tidak ada karyawan yang eligible untuk dicetak.'], 404);
        }

        $zipPath = $this->pdfService->generateBundle($employees);

        return response()->download($zipPath, 'family-gathering-tickets.zip')
                         ->deleteFileAfterSend(true);
    }

    public function downloadSinglePdf(Employee $employee)
    {
        if ($cachedPath = $this->pdfService->cachedPath($employee)) {
            return response()->download($cachedPath, $employee->pdf_filename);
        }

        [$bytes, $filename] = $this->pdfService->renderAndCache($employee);

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function downloadSingleImage(Employee $employee)
    {
        if ($cachedPath = $this->pdfService->cachedImagePath($employee)) {
            return response()->download($cachedPath, basename($cachedPath));
        }

        [$bytes, $filename] = $this->pdfService->renderImageAndCache($employee);

        return response($bytes, 200, [
            'Content-Type'        => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function downloadQrCode(Employee $employee)
    {
        $bytes    = $this->qrService->renderPng($employee);
        $filename = $this->qrService->filename($employee);

        return response($bytes, 200, [
            'Content-Type'        => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
