<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\EmployeePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateEmployeeTicketFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(private readonly int $employeeId) {}

    public function handle(EmployeePdfService $pdfService): void
    {
        $employee = Employee::find($this->employeeId);
        if (!$employee) {
            return;
        }

        $pdfService->renderAndCache($employee);
        $pdfService->renderImageAndCache($employee);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Ticket file generation failed permanently', [
            'employee_id' => $this->employeeId,
            'error'       => $e->getMessage(),
        ]);
    }
}
