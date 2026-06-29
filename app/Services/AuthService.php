<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(private LoginLogService $logService) {}

    /**
     * Attempt login. Returns token payload on success, null on bad credentials.
     */
    public function attempt(string $username, string $password, ?string $ip, ?string $ua): ?array
    {
        $user = User::where('username', $username)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            $this->logService->record(null, $username, 'failed', $ip, $ua);
            return null;
        }

        // Revoke previous tokens so only one active session per user
        $user->tokens()->delete();

        $token = $user->createToken('auth-token', ['*'], now()->addHours(12))->plainTextToken;

        $this->logService->record($user->id, $user->username, 'success', $ip, $ua);

        return [
            'token'        => $token,
            'role'         => $user->role,
            'display_name' => $user->display_name ?? $user->username,
        ];
    }

    public function logout(Authenticatable $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
