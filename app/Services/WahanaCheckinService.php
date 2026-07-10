<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WahanaCheckinService
{
    public function search(string $query): array
    {
        return Employee::search($query)->orderBy('name')->get()
            ->map(fn (Employee $e) => $this->present($e))
            ->all();
    }

    public function lookup(string $code): ?array
    {
        $employee = Employee::where('qr_code', $code)
            ->orWhere('manual_code', Employee::normalizeManualCode($code))
            ->first();

        return $employee ? $this->present($employee) : null;
    }

    /**
     * @return array{success: bool, state: array}
     */
    public function checkin(Employee $employee, string $wahana, ?int $userId): array
    {
        return DB::transaction(function () use ($employee, $wahana, $userId) {
            $locked = Employee::whereKey($employee->id)->lockForUpdate()->first();
            $state  = $this->wahanaState($locked, $wahana);

            if ($state['checked_in'] >= $state['total']) {
                return ['success' => false, 'state' => $state];
            }

            $locked->wahanaCheckins()->create([
                'wahana'        => $wahana,
                'checked_in_by' => $userId,
            ]);

            return ['success' => true, 'state' => $this->wahanaState($locked->fresh(), $wahana)];
        });
    }

    public function present(Employee $employee): array
    {
        $counts = $employee->wahanaCheckins()
            ->selectRaw('wahana, count(*) as cnt')
            ->groupBy('wahana')
            ->pluck('cnt', 'wahana');

        return [
            'id'                 => $employee->id,
            'name'               => $employee->name,
            // Wahana-only display: bumped by the below-2/not-below-1 bonus, never persisted.
            'total_passengers'   => $employee->total_passengers + $this->headcountBonus($employee),
            'additional_members' => $employee->additional_members,
            'checkins'           => [
                'sea_world' => $this->wahanaState($employee, 'sea_world', $counts),
                'samudera'  => $this->wahanaState($employee, 'samudera', $counts),
            ],
        ];
    }

    public function wahanaState(Employee $employee, string $wahana, ?Collection $counts = null): array
    {
        $total     = $employee->total_passengers + $employee->additional_members + $this->headcountBonus($employee);
        $checkedIn = $counts
            ? (int) ($counts[$wahana] ?? 0)
            : $employee->wahanaCheckins()->where('wahana', $wahana)->count();

        return [
            'total'      => $total,
            'checked_in' => $checkedIn,
            'remaining'  => max($total - $checkedIn, 0),
        ];
    }

    /**
     * A child under 2 is already excluded from total_passengers at import (gate rule),
     * but the rides only waive tickets under 1 — so a 1-2 year old needs a wahana seat
     * back. This never touches stored data, only the counts this service computes.
     */
    private function headcountBonus(Employee $employee): int
    {
        return $employee->needsWahanaHeadcountBonus() ? 1 : 0;
    }
}
