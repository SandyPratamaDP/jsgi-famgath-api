<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\WahanaCheckin;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class WahanaCheckinController extends Controller
{
    public function lookup(string $code)
    {
        $employee = Employee::where('qr_code', $code)->first();

        if (!$employee) {
            return response()->json(['message' => 'Kode QR tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $this->present($employee)]);
    }

    public function checkin(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'wahana' => 'required|in:sea_world,samudera',
        ]);

        $existing = $employee->wahanaCheckins()->where('wahana', $data['wahana'])->first();
        if ($existing) {
            return response()->json([
                'message' => 'Wahana ini sudah digunakan.',
                'data'    => $this->presentCheckin($existing),
            ], 409);
        }

        try {
            $checkin = $employee->wahanaCheckins()->create([
                'wahana'        => $data['wahana'],
                'checked_in_by' => $request->user()->id,
            ]);
        } catch (QueryException $e) {
            $existing = $employee->wahanaCheckins()->where('wahana', $data['wahana'])->first();

            return response()->json([
                'message' => 'Wahana ini sudah digunakan.',
                'data'    => $existing ? $this->presentCheckin($existing) : null,
            ], 409);
        }

        return response()->json(['data' => $this->presentCheckin($checkin)], 201);
    }

    private function present(Employee $employee): array
    {
        $checkins = $employee->wahanaCheckins()->with('checkedInByUser')->get()->keyBy('wahana');

        $forWahana = fn (string $w) => $checkins->has($w)
            ? $this->presentCheckin($checkins[$w])
            : ['used' => false, 'checked_in_at' => null, 'checked_in_by' => null];

        return [
            'id'                 => $employee->id,
            'name'               => $employee->name,
            'total_passengers'   => $employee->total_passengers,
            'additional_members' => $employee->additional_members,
            'checkins'           => [
                'sea_world' => $forWahana('sea_world'),
                'samudera'  => $forWahana('samudera'),
            ],
        ];
    }

    private function presentCheckin(WahanaCheckin $checkin): array
    {
        $checkin->loadMissing('checkedInByUser');

        return [
            'used'          => true,
            'checked_in_at' => $checkin->created_at,
            'checked_in_by' => $checkin->checkedInByUser?->display_name
                               ?? $checkin->checkedInByUser?->username,
        ];
    }
}
