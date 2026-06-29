<?php

namespace App\Services;

use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use ZipArchive;

class EmployeePdfService
{
    public function generateBundle(Collection $employees): string
    {
        // PDF generation for 100+ employees can take well over 30s
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        [$logoData, $qrData] = $this->buildImageDataUris();

        $ticketDir = storage_path('app/public/tickets');
        if (!is_dir($ticketDir)) {
            mkdir($ticketDir, 0755, true);
        }

        $zipPath = storage_path('app/public/ticket-bundle.zip');
        $zip     = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($employees as $employee) {
            [$pdfBytes, $filename] = $this->render($employee, $logoData, $qrData);

            file_put_contents($ticketDir . '/' . $filename, $pdfBytes);
            $zip->addFromString($filename, $pdfBytes);
            unset($pdfBytes);

            $employee->update(['pdf_filename' => $filename]);
        }

        $zip->close();

        return $zipPath;
    }

    /**
     * Return the on-disk path if a cached PDF exists, otherwise null.
     */
    public function cachedPath(Employee $employee): ?string
    {
        if (!$employee->pdf_filename) {
            return null;
        }

        $path = storage_path('app/public/tickets/' . $employee->pdf_filename);
        return file_exists($path) ? $path : null;
    }

    /**
     * Render, persist to disk, update the model's pdf_filename, and return [bytes, filename].
     */
    public function renderAndCache(Employee $employee): array
    {
        [$logoData, $qrData] = $this->buildImageDataUris();
        [$pdfBytes, $filename] = $this->render($employee, $logoData, $qrData);

        $ticketDir = storage_path('app/public/tickets');
        if (!is_dir($ticketDir)) {
            mkdir($ticketDir, 0755, true);
        }

        file_put_contents($ticketDir . '/' . $filename, $pdfBytes);
        $employee->update(['pdf_filename' => $filename]);

        return [$pdfBytes, $filename];
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function render(Employee $employee, string $logoData, string $qrData): array
    {
        $pdf = Pdf::loadView('pdf.ticket', [
            'employee' => $employee,
            'logoData' => $logoData,
            'qrData'   => $qrData,
        ]);

        $safeName = preg_replace('/[^a-z0-9]+/', '_', strtolower($employee->name));
        $filename = sprintf('%s_ticket.pdf', $safeName);
        $bytes    = $pdf->output();
        unset($pdf);

        return [$bytes, $filename];
    }

    private function buildImageDataUris(): array
    {
        // Logo: convert webp → png via GD so dompdf can render it reliably
        $logoPath = storage_path('app/public/logo.webp');
        if (file_exists($logoPath) && function_exists('imagecreatefromwebp')) {
            $img = @imagecreatefromwebp($logoPath);
            if ($img) {
                ob_start();
                imagepng($img);
                $logoData = 'data:image/png;base64,' . base64_encode(ob_get_clean());
                unset($img); // imagedestroy deprecated in PHP 8.5+
            } else {
                $logoData = 'data:image/webp;base64,' . base64_encode(file_get_contents($logoPath));
            }
        } elseif (file_exists($logoPath)) {
            $logoData = 'data:image/webp;base64,' . base64_encode(file_get_contents($logoPath));
        } else {
            $logoData = '';
        }

        $qrPath = storage_path('app/public/ancol-qr.png');
        $qrData = file_exists($qrPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($qrPath))
            : '';

        return [$logoData, $qrData];
    }
}
