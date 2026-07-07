<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmployeeTicketEmail;
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

    public function blastEmail()
    {
        // Only private_car/operational employees get emailed tickets (bus/PIC bus print instead),
        // and only those never sent before — safe to click repeatedly as new imports land.
        $employees = Employee::query()
            ->whereIn('transport_type', ['private_car', 'operational'])
            ->whereNotNull('email')
            ->whereNull('ticket_email_sent_at')
            ->orderBy('name')
            ->get();

        $dispatched = 0;

        foreach ($employees as $employee) {
            if (!filter_var($employee->email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            SendEmployeeTicketEmail::dispatch($employee->id);
            $dispatched++;
        }

        return response()->json([
            'message' => 'Blast email dimulai di background.',
            'count'   => $dispatched,
        ]);
    }

    /**
     * Manual/individual (re)send — ignores ticket_email_sent_at so it also covers
     * "didn't receive it" or "the blast failed for them" cases. The job itself only
     * stamps ticket_email_sent_at once the send actually succeeds.
     */
    public function sendEmail(Employee $employee)
    {
        if (!filter_var($employee->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Karyawan ini tidak memiliki alamat email yang valid.'], 422);
        }

        SendEmployeeTicketEmail::dispatch($employee->id);

        return response()->json(['message' => 'Email sedang dikirim di background.']);
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
