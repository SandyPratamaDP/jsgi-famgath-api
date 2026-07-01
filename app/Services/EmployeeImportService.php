<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class EmployeeImportService
{
    public function import(UploadedFile $file): int
    {
        $spreadsheet = IOFactory::createReaderForFile($file->getPathname())
                                ->load($file->getPathname());

        $sheets = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheets[$sheet->getTitle()] = collect($sheet->toArray());
        }

        $busGroups        = $this->buildBusGroups($sheets);
        $privateCarMap    = $this->buildPrivateCarMap($sheets);
        $masterList       = $this->buildMasterList($sheets);
        $belowTwoMap      = $this->buildBelowTwoMap($sheets);
        $additionalMap    = $this->buildAdditionalTicketMap($sheets);
        $emailMap         = $this->buildEmailMap($sheets);
        $records          = $this->buildAllEmployees($masterList, $privateCarMap, $busGroups, $belowTwoMap, $additionalMap, $emailMap);

        foreach ($records as $data) {
            Employee::updateOrCreate(['name' => $data['name']], $data);
        }

        return count($records);
    }

    // ── Sheet parsers ─────────────────────────────────────────────────────────

    /**
     * Read the Bus sheet.
     * Returns [strtolower(pic_name) => ['bus_number', 'total_bus_passengers', 'pickup_point']]
     */
    private function buildBusGroups(array $sheets): array
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
     * Returns [lowercase_name => ['name', 'employee_type', 'total_vehicles']]
     */
    private function buildPrivateCarMap(array $sheets): array
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
     * Read the master sheet. Each row = +1 headcount for that name.
     * Returns [lowercase_name => ['name', 'headcount']]
     */
    private function buildMasterList(array $sheets): array
    {
        $masterList = [];

        foreach ($sheets as $sheetName => $rows) {
            $lower = strtolower($sheetName);
            if (stripos($lower, 'bus')     !== false ||
                stripos($lower, 'pribadi') !== false ||
                stripos($lower, 'local')   !== false ||
                stripos($lower, 'expat')   !== false ||
                stripos($lower, 'email')   !== false ||
                stripos($lower, 'below')   !== false ||
                stripos($lower, 'additional') !== false) continue;

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
     * Read the "Below 2" sheet. Each row is a family member under 2 years old,
     * who doesn't need a ticket. Returns [lowercase_name => count_of_children_under_2]
     */
    private function buildBelowTwoMap(array $sheets): array
    {
        $map = [];

        foreach ($sheets as $sheetName => $rows) {
            if (stripos($sheetName, 'below') === false) continue;

            [$headers, $dataRows] = $this->extractHeadersAndRows($rows);

            foreach ($dataRows as $row) {
                $row  = $this->combineRow($headers, (array) $row);
                $name = $this->col($row, ['nama', 'name', 'emp_name', 'full_name']);
                if (!$name) continue;

                $nameKey = strtolower(trim($name));
                $map[$nameKey] = ($map[$nameKey] ?? 0) + 1;
            }
        }

        return $map;
    }

    /**
     * Read the "Additional Ticket" sheet. Columns: "Nama Lengkap", "Additional Ticket Famgath"
     * (count of extra recreation tickets bought for people outside the employee's core family).
     * Returns [lowercase_name => additional_ticket_count]
     */
    private function buildAdditionalTicketMap(array $sheets): array
    {
        $map = [];

        foreach ($sheets as $sheetName => $rows) {
            if (stripos($sheetName, 'additional') === false) continue;

            [$headers, $dataRows] = $this->extractHeadersAndRows($rows);

            foreach ($dataRows as $row) {
                $row  = $this->combineRow($headers, (array) $row);
                $name = $this->col($row, ['nama_lengkap', 'nama', 'name', 'emp_name', 'full_name']);
                if (!$name) continue;

                $count = (int) ($this->col($row, ['additional_ticket_famgath', 'additional_ticket']) ?: 0);

                $nameKey = strtolower(trim($name));
                $map[$nameKey] = ($map[$nameKey] ?? 0) + $count;
            }
        }

        return $map;
    }

    /**
     * Read the "Data Email" sheet. Columns: NIK, Name, Email Address.
     * Used to send tickets to employees for self-printing.
     * Returns [lowercase_name => email]
     */
    private function buildEmailMap(array $sheets): array
    {
        $map = [];

        foreach ($sheets as $sheetName => $rows) {
            if (stripos($sheetName, 'email') === false) continue;

            [$headers, $dataRows] = $this->extractHeadersAndRows($rows);

            foreach ($dataRows as $row) {
                $row   = $this->combineRow($headers, (array) $row);
                $name  = $this->col($row, ['nama', 'name', 'emp_name', 'full_name']);
                $email = $this->col($row, ['email_address', 'email']);
                if (!$name || !$email) continue;

                $map[strtolower(trim($name))] = $email;
            }
        }

        return $map;
    }

    /**
     * Merge master list + private car map + bus groups + below-2 map + additional-ticket map
     * into final employee records.
     *
     * Expat rule: headcount +1 to account for the company-provided driver.
     * Below-2 rule: headcount -1 per matching child, since infants under 2 don't need a ticket.
     * Additional-ticket rule: additional_members holds guests outside the core family; their
     * vehicles are paid for separately, so they never affect total_vehicles.
     * switched_from_bus is intentionally excluded — import never resets a flag set at the gate.
     */
    private function buildAllEmployees(array $masterList, array $privateCarMap, array $busGroups, array $belowTwoMap = [], array $additionalMap = [], array $emailMap = []): array
    {
        $records = [];

        foreach (array_keys($masterList) as $nameKey) {
            $hasCar = isset($privateCarMap[$nameKey]);

            $name      = $hasCar ? $privateCarMap[$nameKey]['name'] : $masterList[$nameKey]['name'];
            $headcount = max(1, $masterList[$nameKey]['headcount']);

            if ($hasCar) {
                $empType   = $privateCarMap[$nameKey]['employee_type'];
                $vehicles  = $privateCarMap[$nameKey]['total_vehicles'];
                $transport = $vehicles >= 1 ? 'private_car' : 'bus';

                // Expat employees are accompanied by a company driver → add 1 to headcount
                if ($empType === 'expat') {
                    $headcount += 1;
                }
            } else {
                $empType   = 'local';
                $vehicles  = 0;
                $transport = 'bus';
            }

            $headcount = max(0, $headcount - ($belowTwoMap[$nameKey] ?? 0));

            $isPicBus     = isset($busGroups[$nameKey]);
            $busNumber    = $isPicBus ? $busGroups[$nameKey]['bus_number']           : null;
            $totalBusPass = $isPicBus ? $busGroups[$nameKey]['total_bus_passengers'] : null;
            $pickupPoint  = $isPicBus ? $busGroups[$nameKey]['pickup_point']         : null;

            $records[] = [
                'name'                 => $name,
                'email'                => $emailMap[$nameKey] ?? null,
                'employee_type'        => $empType,
                'total_vehicles'       => $vehicles,
                'total_passengers'       => $headcount,
                'additional_members'     => $additionalMap[$nameKey] ?? 0,
                'has_below_two_children' => ($belowTwoMap[$nameKey] ?? 0) > 0,
                'transport_type'       => $transport,
                'bus_number'           => $busNumber,
                'is_pic_bus'           => $isPicBus,
                'total_bus_passengers' => $totalBusPass,
                'pickup_point'         => $pickupPoint,
            ];
        }

        return $records;
    }

    // ── Low-level spreadsheet helpers ─────────────────────────────────────────

    private function extractHeadersAndRows($rows): array
    {
        $knownWords  = ['nik', 'emp nik', 'bus', 'pic', 'no', 'nama', 'name', 'nama lengkap'];
        $headerIndex = 0;

        foreach ($rows as $index => $row) {
            foreach ((array) $row as $cell) {
                if (in_array(strtolower(trim((string) $cell)), $knownWords, true)) {
                    $headerIndex = $index;
                    break 2;
                }
            }
        }

        $nextRow      = (array) ($rows->get($headerIndex + 1) ?? []);
        $hasSubHeader = false;
        foreach ($nextRow as $cell) {
            if (in_array(strtolower(trim((string) $cell)), $knownWords, true)) {
                $hasSubHeader = true;
                break;
            }
        }

        if ($hasSubHeader) {
            $headers = $this->mergeHeaderRows((array) ($rows->get($headerIndex) ?? []), $nextRow);
            return [$headers, $rows->slice($headerIndex + 2)];
        }

        $headers = $this->normalizeHeaders((array) ($rows->get($headerIndex) ?? []));
        return [$headers, $rows->slice($headerIndex + 1)];
    }

    private function mergeHeaderRows(array $row0, array $row1): array
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

    private function combineRow(array $headers, array $rowValues): array
    {
        $headerNames = array_values($headers);
        $count       = count($headerNames);
        $values      = array_slice(array_pad(array_values($rowValues), $count, null), 0, $count);

        $result = [];
        foreach ($headerNames as $i => $name) {
            if ($name !== '' && !array_key_exists($name, $result)) {
                $result[$name] = $values[$i];
            }
        }
        return $result;
    }

    private function normalizeHeaders(array $headerRow): array
    {
        return collect($headerRow)
            ->mapWithKeys(function ($value, $index) {
                $key        = strtolower(trim((string) $value));
                $normalized = preg_replace('/[^a-z0-9]+/', '_', $key);
                $normalized = trim($normalized, '_');
                return [$index => $normalized];
            })
            ->toArray();
    }

    private function col(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($row[$key]) && trim((string) $row[$key]) !== '') {
                return trim((string) $row[$key]);
            }
        }
        return null;
    }
}
