<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\IOFactory;
use ZipArchive;

class EmployeeController extends Controller
{
    // ─── API: list all employees ──────────────────────────────────────────────

    public function index(Request $request)
    {
        $employees = Employee::orderBy('transport_type')
            ->orderByDesc('is_pic_bus')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $employees]);
    }

    // ─── API: search by name ─────────────────────────────────────────────────

    public function search(Request $request)
    {
        $query = trim($request->query('query', ''));
        $employees = Employee::search($query)->orderBy('name')->get();
        return response()->json(['data' => $employees]);
    }

    // ─── API: import from Excel ───────────────────────────────────────────────

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls']);

        $file        = $request->file('file');
        $spreadsheet = IOFactory::createReaderForFile($file->getPathname())
                                ->load($file->getPathname());

        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheets[$sheet->getTitle()] = collect($sheet->toArray());
        }

        // Step 1 ─ extract Bus PIC lookup [pic_name_lower => {bus_number, total_bus_passengers}]
        $busGroups = $this->buildBusGroups($sheets);

        // Step 2 ─ mark private car employees from Pribadi Local / Pribadi Expat
        //          returns [lowercase_name => {name, employee_type, total_vehicles}]
        $privateCarMap = $this->buildPrivateCarMap($sheets);

        // Step 3 ─ count headcount from master sheet by name
        //          one row per person — each occurrence increments headcount by 1
        //          returns [lowercase_name => {name, headcount}]
        $masterList = $this->buildMasterList($sheets);

        // Step 4 ─ merge all sources into final employee records
        $records = $this->buildAllEmployees($masterList, $privateCarMap, $busGroups);

        foreach ($records as $data) {
            Employee::updateOrCreate(['name' => $data['name']], $data);
        }

        return response()->json([
            'message' => 'Import completed successfully',
            'count'   => count($records),
        ]);
    }

    // ─── API: field-level update (vehicle switch) ────────────────────────────

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

        // Mark employees who were originally on the bus but switched on the day-of-event.
        // Only set when switching away from bus — never cleared by this endpoint.
        if (($attributes['transport_type'] ?? null) === 'private_car' && $employee->transport_type === 'bus') {
            $attributes['switched_from_bus'] = true;
        }

        $employee->update($attributes);
        return response()->json(['data' => $employee->fresh()]);
    }

    // ─── API: generate + store + download all PDFs as ZIP ────────────────────

    public function generateAndDownloadPdfs()
    {
        // Override PHP CLI's 30s default — PDF generation for 100+ employees takes longer
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $employees = Employee::query()
            ->where(fn ($q) => $q->where('transport_type', 'private_car')->orWhere('is_pic_bus', true))
            ->orderBy('name')
            ->get();

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'Tidak ada karyawan yang eligible untuk dicetak.'], 404);
        }

        [$logoData, $qrData] = $this->buildImageDataUris();

        $ticketDir = storage_path('app/public/tickets');
        if (!is_dir($ticketDir)) {
            mkdir($ticketDir, 0755, true);
        }

        $zipPath = storage_path('app/public/ticket-bundle.zip');
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($employees as $employee) {
            $pdf = Pdf::loadView('pdf.ticket', [
                'employee' => $employee,
                'logoData' => $logoData,
                'qrData'   => $qrData,
            ]);

            $safeName = preg_replace('/[^a-z0-9]+/', '_', strtolower($employee->name));
            $filename = sprintf('%s_ticket.pdf', $safeName);
            $pdfBytes = $pdf->output();
            unset($pdf);

            file_put_contents($ticketDir . '/' . $filename, $pdfBytes);
            $zip->addFromString($filename, $pdfBytes);
            unset($pdfBytes);

            // Save filename to DB so single-download can serve from storage
            $employee->update(['pdf_filename' => $filename]);
        }

        $zip->close();

        return response()->download($zipPath, 'family-gathering-tickets.zip')
                         ->deleteFileAfterSend(true);
    }

    // ─── API: download single employee PDF (from storage if ready, else generate) ──

    public function downloadSinglePdf(Employee $employee)
    {
        $ticketDir = storage_path('app/public/tickets');
        $storedPath = $employee->pdf_filename
            ? $ticketDir . '/' . $employee->pdf_filename
            : null;

        // Serve cached file if it exists on disk
        if ($storedPath && file_exists($storedPath)) {
            return response()->download($storedPath, $employee->pdf_filename);
        }

        // Not yet generated — render on the fly and cache it
        [$logoData, $qrData] = $this->buildImageDataUris();

        $pdf = Pdf::loadView('pdf.ticket', [
            'employee' => $employee,
            'logoData' => $logoData,
            'qrData'   => $qrData,
        ]);

        $safeName = preg_replace('/[^a-z0-9]+/', '_', strtolower($employee->name));
        $filename = sprintf('%s_ticket.pdf', $safeName);
        $pdfBytes = $pdf->output();
        unset($pdf);

        if (!is_dir($ticketDir)) {
            mkdir($ticketDir, 0755, true);
        }

        file_put_contents($ticketDir . '/' . $filename, $pdfBytes);
        $employee->update(['pdf_filename' => $filename]);

        return response($pdfBytes, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ─── Helper: build base64 data URIs for logo + QR ─────────────────────────

    protected function buildImageDataUris(): array
    {
        // Logo: convert webp → png via GD so dompdf can render it reliably
        $logoPath = storage_path('app/public/logo.webp');
        if (file_exists($logoPath) && function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($logoPath);
            if ($img) {
                ob_start();
                imagepng($img);
                $logoData = 'data:image/png;base64,' . base64_encode(ob_get_clean());
                unset($img); // imagedestroy deprecated in PHP 8.5+
            } else {
                $logoData = 'data:image/webp;base64,' . base64_encode(file_get_contents($logoPath));
            }
        } elseif (file_exists($logoPath)) {
            $logoData = 'data:image/webp;base64,' . base64_encode(file_get_contents($logoPath));
        } else {
            $logoData = '';
        }

        $qrPath = storage_path('app/public/ancol-qr.png');
        $qrData = file_exists($qrPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($qrPath))
            : '';

        return [$logoData, $qrData];
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // IMPORT HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Read the Bus sheet.
     * Expected columns: BUS (number), PIC (coordinator name), Total Penumpang.
     * Returns [strtolower(pic_name) => ['bus_number' => int, 'total_bus_passengers' => int]]
     */
    protected function buildBusGroups(array $sheets): array
    {
        $busGroups = [];

        foreach ($sheets as $sheetName => $rows) {
            if (stripos($sheetName, 'bus') === false) continue;

            [$headers, $dataRows] = $this->extractHeadersAndRows($rows);

            foreach ($dataRows as $row) {
                $row    = $this->combineRow($headers, (array) $row);
                $pic    = $this->col($row, ['pic']);
                $busNum = $this->col($row, ['bus', 'bus_number', 'no_bus', 'nomor_bus']);
                $total  = $this->col($row, ['total_penumpang', 'total_penumpang_eo_koord', 'jumlah_penumpang']);

                $pickup = $this->col($row, ['titik_jemputan', 'pickup_point', 'titik_penjemputan', 'lokasi_jemputan']);

                if ($pic && is_numeric($busNum)) {
                    $busGroups[strtolower(trim($pic))] = [
                        'bus_number'           => (int) $busNum,
                        'total_bus_passengers' => (int) ($total ?: 0),
                        'pickup_point'         => $pickup,
                    ];
                }
            }
        }

        return $busGroups;
    }

    /**
     * Read Pribadi Local and Pribadi Expat sheets.
     * Only marks who uses private car and how many vehicles — no headcount taken here.
     * Returns [lowercase_name => ['name' => string, 'employee_type' => string, 'total_vehicles' => int]]
     */
    protected function buildPrivateCarMap(array $sheets): array
    {
        $map = [];

        foreach ($sheets as $sheetName => $rows) {
            $lower   = strtolower($sheetName);
            $isExpat = stripos($lower, 'expat') !== false;

            if (stripos($lower, 'pribadi') === false &&
                stripos($lower, 'local')   === false &&
                stripos($lower, 'expat')   === false) continue;

            [$headers, $dataRows] = $this->extractHeadersAndRows($rows);

            foreach ($dataRows as $row) {
                $row  = $this->combineRow($headers, (array) $row);
                $name = $this->col($row, ['nama', 'name', 'emp_name', 'full_name']);
                if (!$name) continue;

                $vehicles = (int) ($this->col($row, ['jumlah_kendaraan', 'total_vehicles', 'vehicle_count']) ?: 0);

                $map[strtolower(trim($name))] = [
                    'name'           => $name,
                    'employee_type'  => $isExpat ? 'expat' : 'local',
                    'total_vehicles' => $vehicles,
                ];
            }
        }

        return $map;
    }

    /**
     * Read the master sheet ("Data Terakhir Kary. ikut FamGat").
     * Only reads the name column. Each row = +1 headcount for that name.
     * Returns [lowercase_name => ['name' => string, 'headcount' => int]]
     */
    protected function buildMasterList(array $sheets): array
    {
        $masterList = [];

        foreach ($sheets as $sheetName => $rows) {
            $lower = strtolower($sheetName);
            if (stripos($lower, 'bus')     !== false ||
                stripos($lower, 'pribadi') !== false ||
                stripos($lower, 'local')   !== false ||
                stripos($lower, 'expat')   !== false ||
                stripos($lower, 'email')   !== false) continue;

            [$headers, $dataRows] = $this->extractHeadersAndRows($rows);

            foreach ($dataRows as $row) {
                $row  = $this->combineRow($headers, (array) $row);
                $name = $this->col($row, ['nama', 'name', 'emp_name', 'full_name']);
                if (!$name) continue;

                $nameKey = strtolower(trim($name));
                if (!isset($masterList[$nameKey])) {
                    $masterList[$nameKey] = ['name' => $name, 'headcount' => 0];
                }
                $masterList[$nameKey]['headcount']++;
            }
        }

        return $masterList;
    }

    /**
     * Combine master list + private car map + bus groups into the final record set.
     * Both maps are keyed by lowercase name. Headcount always comes from the master list.
     *
     * Expat rule: headcount +1 to account for the company-provided driver.
     * Local rule: transport status updated only — headcount unchanged from master list.
     */
    protected function buildAllEmployees(array $masterList, array $privateCarMap, array $busGroups): array
    {
        // Only iterate names from master sheet — Pribadi sheet only updates transport, never inserts
        $records = [];

        foreach (array_keys($masterList) as $nameKey) {
            $hasMaster = true;
            $hasCar    = isset($privateCarMap[$nameKey]);

            $name      = $hasCar ? $privateCarMap[$nameKey]['name'] : $masterList[$nameKey]['name'];
            $headcount = $hasMaster ? max(1, $masterList[$nameKey]['headcount']) : 1;

            if ($hasCar) {
                $empType  = $privateCarMap[$nameKey]['employee_type'];
                $vehicles = $privateCarMap[$nameKey]['total_vehicles'];
                $transport = $vehicles >= 1 ? 'private_car' : 'bus';

                // Expat employees are accompanied by a company driver → add 1 to headcount
                if ($empType === 'expat') {
                    $headcount += 1;
                }
            } else {
                $empType  = 'local';
                $vehicles = 0;
                $transport = 'bus';
            }

            // Cross-reference with Bus PIC list ($nameKey is already lowercase)
            $isPicBus     = isset($busGroups[$nameKey]);
            $busNumber    = $isPicBus ? $busGroups[$nameKey]['bus_number']           : null;
            $totalBusPass = $isPicBus ? $busGroups[$nameKey]['total_bus_passengers'] : null;
            $pickupPoint  = $isPicBus ? $busGroups[$nameKey]['pickup_point']         : null;

            $records[] = [
                'name'                 => $name,
                'employee_type'        => $empType,
                'total_vehicles'       => $vehicles,
                'total_passengers'     => $headcount,
                'transport_type'       => $transport,
                'bus_number'           => $busNumber,
                'is_pic_bus'           => $isPicBus,
                'total_bus_passengers' => $totalBusPass,
                'pickup_point'         => $pickupPoint,
            ];
        }

        return $records;
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // LOW-LEVEL SPREADSHEET HELPERS
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Find the actual header row in a sheet, handling:
     *  - Sheets with a title/date in the first row (master sheet)
     *  - Sheets with merged-cell double headers (Bus sheet)
     *
     * Returns [$normalizedHeaders, $dataRowsCollection]
     */
    protected function extractHeadersAndRows($rows): array
    {
        // Cells in a real header row always contain one of these keywords
        $knownWords = ['nik', 'emp nik', 'bus', 'pic', 'no', 'nama', 'name'];

        $headerIndex = 0;
        foreach ($rows as $index => $row) {
            foreach ((array) $row as $cell) {
                if (in_array(strtolower(trim((string) $cell)), $knownWords, true)) {
                    $headerIndex = $index;
                    break 2;
                }
            }
        }

        // Detect Bus-style double headers: merged cell row + sub-header row
        $nextRow      = (array) ($rows->get($headerIndex + 1) ?? []);
        $hasSubHeader = false;
        foreach ($nextRow as $cell) {
            if (in_array(strtolower(trim((string) $cell)), $knownWords, true)) {
                $hasSubHeader = true;
                break;
            }
        }

        if ($hasSubHeader) {
            $headers  = $this->mergeHeaderRows(
                (array) ($rows->get($headerIndex) ?? []),
                $nextRow
            );
            return [$headers, $rows->slice($headerIndex + 2)];
        }

        $headers = $this->normalizeHeaders((array) ($rows->get($headerIndex) ?? []));
        return [$headers, $rows->slice($headerIndex + 1)];
    }

    /**
     * Combine two header rows (main + sub-header for merged-cell columns).
     * Sub-header value takes priority over main header if non-empty.
     */
    protected function mergeHeaderRows(array $row0, array $row1): array
    {
        $maxLen = max(count($row0), count($row1));
        $merged = [];
        for ($i = 0; $i < $maxLen; $i++) {
            $v0       = trim((string) ($row0[$i] ?? ''));
            $v1       = trim((string) ($row1[$i] ?? ''));
            $merged[$i] = $v1 !== '' ? $v1 : $v0;
        }
        return $this->normalizeHeaders($merged);
    }

    /**
     * BUG FIX: combine column headers (names) with row values.
     * Original bug: used array_keys($headers) = [0,1,2...] as keys,
     * so normalizeValue could never match any string key.
     */
    protected function combineRow(array $headers, array $rowValues): array
    {
        $headerNames = array_values($headers); // ← use names, not integer indices
        $count       = count($headerNames);
        $values      = array_slice(array_pad(array_values($rowValues), $count, null), 0, $count);

        $result = [];
        foreach ($headerNames as $i => $name) {
            // Skip empty-string headers; keep only first occurrence of duplicate names
            if ($name !== '' && !array_key_exists($name, $result)) {
                $result[$name] = $values[$i];
            }
        }
        return $result;
    }

    /**
     * Normalize a raw header row to lowercase snake_case keys.
     * Consecutive non-alphanumeric characters collapse into a single underscore.
     * "Total Penumpang (EO + Koord.)" → "total_penumpang_eo_koord"
     */
    protected function normalizeHeaders(array $headerRow): array
    {
        return collect($headerRow)
            ->mapWithKeys(function ($value, $index) {
                $key        = strtolower(trim((string) $value));
                $normalized = preg_replace('/[^a-z0-9]+/', '_', $key); // collapse multiple non-alphanum
                $normalized = trim($normalized, '_');
                return [$index => $normalized];
            })
            ->toArray();
    }

    /**
     * Return the first non-empty value from $row whose key matches one of $keys.
     */
    protected function col(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }
        return null;
    }
}
