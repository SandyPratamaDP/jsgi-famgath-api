<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * @param array<int, array{name: string, contentType: string, contentBytes: string}> $attachments
     */
    public function sendEmail(string $to, string $subject, string $html, array $attachments = []): bool
    {
        $payload = [
            'type'              => 'email',
            'userPrincipalName' => config('services.notify.sender'),
            'subject'           => $subject,
            'content'           => $html,
            'contentType'       => 'Html',
            'toRecipients'      => [$to],
            'ccRecipients'      => [],
            'bccRecipients'     => [],
            'saveToSentItems'   => false,
            'attachments'       => $attachments,
        ];

        $response = Http::withHeaders(['X-Api-Key' => config('services.notify.api_key')])
            ->timeout(30)
            ->post(config('services.notify.url'), $payload);

        if ($response->failed()) {
            Log::warning('Notify service email send failed', [
                'to'     => $to,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->successful();
    }
}
