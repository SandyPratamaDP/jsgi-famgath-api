<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\EmployeePdfService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmployeeTicketEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(private readonly int $employeeId) {}

    public function handle(EmployeePdfService $pdfService, NotificationService $notifier): void
    {
        $employee = Employee::find($this->employeeId);

        if (!$employee || !filter_var($employee->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Self-heal: a manual/individual resend can land before the background
        // generation job has run, so generate on demand instead of skipping.
        if (!$pdfService->cachedImagePath($employee)) {
            $pdfService->renderImageAndCache($employee);
        }

        $pdfPath   = $pdfService->cachedPath($employee);
        $imagePath = $pdfService->cachedImagePath($employee);

        if (!$pdfPath || !$imagePath) {
            Log::warning('Ticket files missing, skipping ticket email', ['employee_id' => $employee->id]);
            return;
        }

        $attachments = [
            [
                'name'         => basename($pdfPath),
                'contentType'  => 'application/pdf',
                'contentBytes' => base64_encode(file_get_contents($pdfPath)),
            ],
            [
                'name'         => basename($imagePath),
                'contentType'  => 'image/png',
                'contentBytes' => base64_encode(file_get_contents($imagePath)),
            ],
        ];

        $html = view('emails.ticket', ['employee' => $employee])->render();

        $sent = $notifier->sendEmail($employee->email, 'Tiket Family Gathering JSGI 2026', $html, $attachments);

        if (!$sent) {
            // Let the job retry/fail properly instead of silently marking as sent —
            // NotificationService already logs the response detail on failure.
            throw new \RuntimeException("Notify service rejected the ticket email for employee {$employee->id}");
        }

        $employee->update(['ticket_email_sent_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Ticket email job failed permanently', [
            'employee_id' => $this->employeeId,
            'error'       => $e->getMessage(),
        ]);
    }
}
