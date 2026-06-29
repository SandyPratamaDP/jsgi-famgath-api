<?php

namespace App\Services;

use App\Models\LoginLog;

class LoginLogService
{
    public function record(?int $userId, string $username, string $status, ?string $ip, ?string $ua): void
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

    private function resolveGeo(?string $ip): array
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
