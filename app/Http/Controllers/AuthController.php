<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $payload = $this->authService->attempt(
            $request->input('username'),
            $request->input('password'),
            $request->ip(),
            $request->userAgent(),
        );

        if (!$payload) {
            return response()->json(['message' => 'Username atau password salah.'], 401);
        }

        return response()->json($payload);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id'           => $user->id,
            'username'     => $user->username,
            'role'         => $user->role,
            'display_name' => $user->display_name ?? $user->username,
        ]);
    }
}
