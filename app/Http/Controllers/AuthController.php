<?php

namespace App\Http\Controllers;

use App\Models\LoginLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $ip        = $request->ip();
        $userAgent = $request->userAgent();
        $user      = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->writeLog(null, $request->username, 'failed', $ip, $userAgent);

            return response()->json(['message' => 'Username atau password salah.'], 401);
        }

        // Revoke previous tokens so only one active session per user
        $user->tokens()->delete();

        $token = $user->createToken('auth-token', ['*'], now()->addHours(12))->plainTextToken;

        $this->writeLog($user->id, $user->username, 'success', $ip, $userAgent);

        return response()->json([
            'token'        => $token,
            'role'         => $user->role,
            'display_name' => $user->display_name ?? $user->username,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        return response()->json([
            'id'           => $request->user()->id,
            'username'     => $request->user()->username,
            'role'         => $request->user()->role,
            'display_name' => $request->user()->display_name ?? $request->user()->username,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function writeLog(?int $userId, string $username, string $status, ?string $ip, ?string $ua): void
    {
        $geo = $this->resolveGeo($ip);

        LoginLog::create([
            'user_id'    => $userId,
            'username'   => $username,
            'status'     => $status,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'city'       => $geo['city']    ?? null,
            'region'     => $geo['region']  ?? null,
            'country'    => $geo['country'] ?? null,
            'isp'        => $geo['isp']     ?? null,
            'logged_at'  => now(),
        ]);
    }

    protected function resolveGeo(?string $ip): array
    {
        // Skip loopback / private ranges — nothing to resolve
        if (!$ip || $ip === '127.0.0.1' || str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return ['city' => 'Local', 'region' => null, 'country' => null, 'isp' => null];
        }

        try {
            $ctx  = stream_context_create(['http' => ['timeout' => 2]]);
            $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,city,regionName,country,isp", false, $ctx);
            $data = $json ? json_decode($json, true) : [];

            if (($data['status'] ?? '') === 'success') {
                return [
                    'city'    => $data['city']       ?? null,
                    'region'  => $data['regionName'] ?? null,
                    'country' => $data['country']    ?? null,
                    'isp'     => $data['isp']        ?? null,
                ];
            }
        } catch (\Throwable) {
            // Geo lookup is best-effort — never block login
        }

        return [];
    }
}
