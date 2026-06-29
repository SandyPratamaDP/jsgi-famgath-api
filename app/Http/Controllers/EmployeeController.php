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

    // ─── API: search by name or NIK ──────────────────────────────────────────

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

        // Step 2 ─ extract private car employees from Pribadi Local / Pribadi Expat
        //          returns [nik => {name, department, employee_type, vehicles, headcount, transport_type}]
        $privateCarMap = $this->buildPrivateCarMap($sheets);

        // Step 3 ─ extract ALL unique employees from the master attendance sheet
        //          ("Data Terakhir Kary. ikut FamGat") — one row per family member
        //          returns [nik => {name, headcount}]
        $masterList = $this->buildMasterList($sheets);

        // Step 4 ─ merge all sources into final employee records
        $records = $this->buildAllEmployees($masterList, $privateCarMap, $busGroups);

        foreach ($records as $data) {
            Employee::updateOrCreate(['nik' => $data['nik']], $data);
        }

        return response()->json([
            'message' => 'Import completed successfully',
            'count'   => count($records),
        ]);
    }

    // ─── API: field-level update (attendance, vehicle switch) ─────────────────

    public function update(Request $request, Employee $employee)
    {
        $attributes = $request->validate([
            'total_vehicles'    => 'sometimes|integer|min:0',
            'transport_type'    => 'sometimes|in:private_car,bus',
            'attendance_status' => 'sometimes|in:absent,present',
            'total_passengers'  => 'sometimes|integer|min:1',
        ]);

        if (array_key_exists('total_vehicles', $attributes) && !array_key_exists('transport_type', $attributes)) {
            $attributes['transport_type'] = $attributes['total_vehicles'] >= 1 ? 'private_car' : 'bus';
        }

        if (($attributes['attendance_status'] ?? null) === 'present' && $employee->attendance_status !== 'present') {
            $attributes['scanned_at'] = now();
        }

        $employee->update($attributes);
        return response()->json(['data' => $employee->fresh()]);
    }

    // ─── API: bulk PDF download (private car + bus PIC only) ─────────────────

    public function downloadBulkPdfs(Request $request)
    {
        $employees = Employee::query()
            ->where(fn ($q) => $q->where('transport_type', 'private_car')->orWhere('is_pic_bus', true))
            ->orderBy('name')
            ->get();

        if ($employees->isEmpty()) {
            return response()->json(['message' => 'No eligible employees found for PDF export.'], 404);
        }

        $zipPath = storage_path('app/public/ticket-bundle.zip');
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($employees as $employee) {
            $pdf      = Pdf::loadView('pdf.ticket', ['employee' => $employee]);
            $filename = sprintf('%s_%s_ticket.pdf', $employee->nik, str_replace(' ', '_', strtolower($employee->name)));
            $zip->addFromString($filename, $pdf->output());
        }

        $zip->close();
        return response()->download($zipPath, 'family-gathering-tickets.zip')->deleteFileAfterSend(true);
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
     * Returns [nik => [...employee fields...]]
     */
    protected function buildPrivateCarMap(array $sheets): array
    {
        $map = [];

        foreach ($sheets as $sheetName => $rows) {
            $lower = strtolower($sheetName);
            if (stripos($lower, 'pribadi') === false &&
                stripos($lower, 'local')   === false &&
                stripos($lower, 'expat')   === false) continue;

            $isExpat = stripos($lower, 'expat') !== false;
            [$headers, $dataRows] = $this->extractHeadersAndRows($rows);

            foreach ($dataRows as $row) {
                $row  = $this->combineRow($headers, (array) $row);
                $nik  = $this->col($row, ['nik', 'emp_nik', 'employee_id', 'no_induk']);
                $name = $this->col($row, ['nama', 'name', 'emp_name', 'full_name']);
                if (!$nik || !$name) continue;

                $vehicles  = (int) ($this->col($row, ['jumlah_kendaraan', 'total_vehicles', 'vehicle_count']) ?: 0);
                $headcount = (int) ($this->col($row, ['jumlah_anggota_keluarga', 'family_member', 'total_passengers', 'jumlah_penumpang', 'headcount']) ?: 1);
                $dept      = $this->col($row, ['department', 'departemen', 'unit']) ?: 'Unknown';

                $map[$nik] = [
                    'name'          => $name,
                    'department'    => $dept,
                    'employee_type' => $isExpat ? 'expat' : 'local',
                    'total_vehicles'  => $vehicles,
                    'total_passengers' => $headcount,
                    'transport_type'  => $vehicles >= 1 ? 'private_car' : 'bus',
                ];
            }
        }

        return $map;
    }

    /**
     * Read the master attendance sheet ("Data Terakhir Kary. ikut FamGat").
     * That sheet has one row per family member, so we deduplicate by NIK
     * and count rows as headcount.
     * Returns [nik => ['name' => string, 'headcount' => int]]
     */
    protected function buildMasterList(array $sheets): array
    {
        $masterList = [];

        foreach ($sheets as $sheetName => $rows) {
            $lower = strtolower($sheetName);
            // Skip the specialised sheets we already handle
            if (stripos($lower, 'bus')    !== false ||
                stripos($lower, 'pribadi') !== false ||
                stripos($lower, 'local')   !== false ||
                stripos($lower, 'expat')   !== false ||
                stripos($lower, 'email')   !== false) continue;

            [$headers, $dataRows] = $this->extractHeadersAndRows($rows);

            foreach ($dataRows as $row) {
                $row  = $this->combineRow($headers, (array) $row);
                $nik  = $this->col($row, ['nik', 'emp_nik', 'employee_id', 'no_induk']);
                $name = $this->col($row, ['nama', 'name', 'emp_name', 'full_name']);
                if (!$nik || !$name) continue;

                if (!isset($masterList[$nik])) {
                    $masterList[$nik] = ['name' => $name, 'headcount' => 0];
                }
                // Each row represents one family member (including the employee themselves)
                $masterList[$nik]['headcount']++;
            }
        }

        return $masterList;
    }

    /**
     * Combine master list + private car map + bus groups into the final record set.
     */
    protected function buildAllEmployees(array $masterList, array $privateCarMap, array $busGroups): array
    {
        $allNiks = array_unique(array_merge(array_keys($masterList), array_keys($privateCarMap)));
        $records = [];

        foreach ($allNiks as $nik) {
            if (isset($privateCarMap[$nik])) {
                $pc        = $privateCarMap[$nik];
                $name      = $pc['name'];
                $dept      = $pc['department'];
                $empType   = $pc['employee_type'];
                $vehicles  = $pc['total_vehicles'];
                $headcount = $pc['total_passengers'];
                $transport = $pc['transport_type'];
            } else {
                $master    = $masterList[$nik];
                $name      = $master['name'];
                $dept      = 'Unknown';
                // Infer employee type from NIK prefix (1xxxxx = expat, 2xxxxx = local)
                $empType   = str_starts_with((string) $nik, '1') ? 'expat' : 'local';
                $vehicles  = 0;
                $headcount = max(1, $master['headcount']);
                $transport = 'bus';
            }

            // Cross-reference with Bus PIC list
            $nameKey         = strtolower(trim($name));
            $isPicBus        = isset($busGroups[$nameKey]);
            $busNumber       = $isPicBus ? $busGroups[$nameKey]['bus_number']           : null;
            $totalBusPass    = $isPicBus ? $busGroups[$nameKey]['total_bus_passengers'] : null;
            $pickupPoint     = $isPicBus ? $busGroups[$nameKey]['pickup_point']         : null;

            $records[] = [
                'nik'                  => $nik,
                'name'                 => $name,
                'department'           => $dept,
                'employee_type'        => $empType,
                'total_vehicles'       => $vehicles,
                'total_passengers'     => $headcount,
                'transport_type'       => $transport,
                'bus_number'           => $busNumber,
                'is_pic_bus'           => $isPicBus,
                'total_bus_passengers' => $totalBusPass,
                'pickup_point'         => $pickupPoint,
                'attendance_status'    => 'absent',
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
