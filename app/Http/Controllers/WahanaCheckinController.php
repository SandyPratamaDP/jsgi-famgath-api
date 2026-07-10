<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\WahanaCheckinService;
use Illuminate\Http\Request;

class WahanaCheckinController extends Controller
{
    public function __construct(private WahanaCheckinService $wahanaCheckinService) {}

    public function search(Request $request)
    {
        $data = $this->wahanaCheckinService->search(trim($request->query('query', '')));

        return response()->json(['data' => $data]);
    }

    public function lookup(string $code)
    {
        $data = $this->wahanaCheckinService->lookup($code);

        if (!$data) {
            return response()->json(['message' => 'Kode tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $data]);
    }

    public function checkin(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'wahana' => 'required|in:sea_world,samudera',
        ]);

        $result = $this->wahanaCheckinService->checkin($employee, $data['wahana'], $request->user()->id);

        if (!$result['success']) {
            return response()->json([
                'message' => 'Kuota wahana ini sudah habis.',
                'data'    => $result['state'],
            ], 409);
        }

        return response()->json(['data' => $result['state']], 201);
    }
}
