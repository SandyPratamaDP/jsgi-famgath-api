<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateEmployeeTicketFiles;
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
            'total_vehicles'         => 'sometimes|integer|min:0',
            'transport_type'         => 'sometimes|in:private_car,bus',
            'total_passengers'       => 'sometimes|integer|min:1',
            'switched_from_bus'      => 'sometimes|boolean',
            'total_bus_passengers'   => 'sometimes|integer|min:0',
            'additional_members'     => 'sometimes|integer|min:0',
            'additional_vehicles'    => 'sometimes|integer|min:0',
            'has_below_two_children' => 'sometimes|boolean',
            'has_below_one_year_child' => 'sometimes|boolean',
        ]);

        if (array_key_exists('total_vehicles', $attributes) && !array_key_exists('transport_type', $attributes)) {
            $attributes['transport_type'] = $attributes['total_vehicles'] >= 1 ? 'private_car' : 'bus';
        }

        // Domain rule: gate-scanner flags bus -> private_car as a day-of anomaly. Callers
        // that already know better (e.g. an admin's manual reassignment) pass
        // switched_from_bus explicitly and that choice wins here.
        if (
            ($attributes['transport_type'] ?? null) === 'private_car'
            && $employee->transport_type === 'bus'
            && !array_key_exists('switched_from_bus', $attributes)
        ) {
            $attributes['switched_from_bus'] = true;
        }

        $employee->update($attributes);

        // Ticket-relevant data changed (e.g. an admin adjusted participant/vehicle
        // counts) — the cached PDF/PNG is now stale, so refresh it the same way a
        // re-import would, instead of leaving it silently out of date.
        if ($employee->wasChanged(Employee::TICKET_RELEVANT_FIELDS)) {
            $resets = [];
            if ($employee->ticket_email_sent_at) {
                $resets['ticket_email_sent_at'] = null;
            }
            if ($employee->isTicketEligible()) {
                $resets['pdf_filename'] = null;
            }
            if ($resets) {
                $employee->update($resets);
            }
            if ($employee->isTicketEligible()) {
                GenerateEmployeeTicketFiles::dispatch($employee->id);
            }
        }

        return response()->json(['data' => $employee->fresh()]);
    }

    public function blastEmail()
    {
        // Emailed tickets go to private_car/operational employees plus PIC bus (who
        // hold their bus's manifest ticket) — regular bus riders have no individual
        // ticket. Only those never sent before — safe to click repeatedly as new
        // imports land.
        $employees = Employee::query()
            ->ticketEligible()
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

    /**
     * Force a fresh render for one employee — for when their ticket-relevant data
     * (e.g. participant count) changed without a full Excel re-import. Nulls
     * pdf_filename first so the employee list's status dot shows "waiting" until
     * the queued job finishes re-rendering.
     */
    public function regenerateSingle(Employee $employee)
    {
        if (!$employee->isTicketEligible()) {
            return response()->json(['message' => 'Karyawan ini tidak memiliki tiket individual.'], 422);
        }

        $employee->update(['pdf_filename' => null]);
        GenerateEmployeeTicketFiles::dispatch($employee->id);

        return response()->json(['message' => 'Tiket sedang digenerate ulang di background.']);
    }

    /**
     * Force a fresh render for every ticket-eligible employee — for bulk corrections
     * (e.g. many employees' participant counts changed) without needing a full
     * Excel re-import.
     */
    public function regenerateAll()
    {
        $employees = Employee::query()->ticketEligible()->get();

        Employee::whereIn('id', $employees->pluck('id'))->update(['pdf_filename' => null]);

        foreach ($employees as $employee) {
            GenerateEmployeeTicketFiles::dispatch($employee->id);
        }

        return response()->json([
            'message' => 'Regenerasi tiket dimulai di background.',
            'count'   => $employees->count(),
        ]);
    }

    /**
     * The underlying file on disk is overwritten in place whenever a ticket is
     * (re)generated, with the same filename and no ETag — without an explicit
     * no-store, browsers apply heuristic freshness off Last-Modified and will
     * silently keep serving a pre-edit download for hours without even asking
     * the server, hiding a just-regenerated ticket.
     */
    private const NO_CACHE_HEADERS = ['Cache-Control' => 'no-store, must-revalidate'];

    public function downloadSinglePdf(Employee $employee)
    {
        if ($cachedPath = $this->pdfService->cachedPath($employee)) {
            return response()->download($cachedPath, $employee->pdf_filename, self::NO_CACHE_HEADERS);
        }

        [$bytes, $filename] = $this->pdfService->renderAndCache($employee);

        return response($bytes, 200, self::NO_CACHE_HEADERS + [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function downloadSingleImage(Employee $employee)
    {
        if ($cachedPath = $this->pdfService->cachedImagePath($employee)) {
            return response()->download($cachedPath, basename($cachedPath), self::NO_CACHE_HEADERS);
        }

        [$bytes, $filename] = $this->pdfService->renderImageAndCache($employee);

        return response($bytes, 200, self::NO_CACHE_HEADERS + [
            'Content-Type'        => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Blank printable form for manual walk-in use — not tied to any Employee record.
     */
    public function blankTicketForm()
    {
        $bytes = $this->pdfService->renderBlankForm();

        return response($bytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="tiket_kosong.pdf"',
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
