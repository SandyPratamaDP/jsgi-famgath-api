<?php

namespace App\Services;

use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;

class EmployeePdfService
{
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
     * Return the on-disk path if a cached PNG image exists, otherwise null.
     */
    public function cachedImagePath(Employee $employee): ?string
    {
        if (!$employee->pdf_filename) {
            return null;
        }

        $path = storage_path('app/public/tickets/' . $this->imageFilename($employee->pdf_filename));
        return file_exists($path) ? $path : null;
    }

    /**
     * Render PNG from PDF (generating+caching the PDF if needed), return [bytes, filename].
     */
    public function renderImageAndCache(Employee $employee): array
    {
        // Reuse cached PDF bytes if available, otherwise render and cache first.
        if ($cachedPdf = $this->cachedPath($employee)) {
            $pdfBytes    = file_get_contents($cachedPdf);
            $pdfFilename = $employee->pdf_filename;
        } else {
            [$pdfBytes, $pdfFilename] = $this->renderAndCache($employee);
        }

        $imageName = $this->imageFilename($pdfFilename);
        $pngBytes  = $this->convertPdfToPng($pdfBytes);

        $ticketDir = storage_path('app/public/tickets');
        if (!is_dir($ticketDir)) {
            mkdir($ticketDir, 0755, true);
        }

        file_put_contents($ticketDir . '/' . $imageName, $pngBytes);

        return [$pngBytes, $imageName];
    }

    /**
     * Render, persist to disk, update the model's pdf_filename, and return [bytes, filename].
     */
    public function renderAndCache(Employee $employee): array
    {
        $logoData = $this->buildLogoDataUri();
        $qrData   = $this->buildQrDataUri($employee);
        [$pdfBytes, $filename] = $this->render($employee, $logoData, $qrData);

        $ticketDir = storage_path('app/public/tickets');
        if (!is_dir($ticketDir)) {
            mkdir($ticketDir, 0755, true);
        }

        file_put_contents($ticketDir . '/' . $filename, $pdfBytes);
        $employee->update(['pdf_filename' => $filename]);

        return [$pdfBytes, $filename];
    }

    /**
     * Delete any PDF/PNG in the tickets dir that no employee references anymore —
     * e.g. left behind after a re-import drops an employee's eligibility or renames them.
     * Returns the number of files removed.
     */
    public function pruneOrphanedFiles(): int
    {
        $ticketDir = storage_path('app/public/tickets');
        if (!is_dir($ticketDir)) {
            return 0;
        }

        $referenced = [];
        foreach (Employee::whereNotNull('pdf_filename')->pluck('pdf_filename') as $pdfFilename) {
            $referenced[$pdfFilename] = true;
            $referenced[$this->imageFilename($pdfFilename)] = true;
        }

        $deleted = 0;
        foreach (scandir($ticketDir) as $file) {
            if ($file === '.' || $file === '..' || isset($referenced[$file])) {
                continue;
            }

            unlink($ticketDir . '/' . $file);
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Render a blank printable form for manual walk-in use: same "Kendaraan Pribadi" card
     * layout as a real ticket, but with the name/vehicle/passenger fields left empty for
     * panitia to fill in by hand. Not tied to any Employee record. The QR is the same local
     * (server-stored, not fetched from an external API) Ancol gate-entry image already used
     * for the "local" employee category on real tickets. Not cached — generated fresh each time.
     */
    public function renderBlankForm(): string
    {
        $pdf = Pdf::loadView('pdf.ticket-blank-form', [
            'logoData' => $this->buildLogoDataUri(),
            'qrData'   => $this->ancolQrDataUri('local'),
        ]);

        $bytes = $pdf->output();
        unset($pdf);

        return $bytes;
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function imageFilename(string $pdfFilename): string
    {
        return str_replace('.pdf', '.png', $pdfFilename);
    }

    private function convertPdfToPng(string $pdfBytes): string
    {
        $imagick = new \Imagick();
        $imagick->setResolution(200, 200);
        $imagick->readImageBlob($pdfBytes);
        $imagick->setImageBackgroundColor('white');
        $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $imagick->setImageFormat('png');
        $png = $imagick->getImageBlob();
        $imagick->clear();
        return $png;
    }

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

    private function buildLogoDataUri(): string
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

        return $logoData;
    }

    /**
     * Each employee category has its own Ancol gate-entry QR: operational staff
     * enter/exit repeatedly so they get a distinct multi-entry QR, and local vs
     * expat get separate single-entry QRs managed independently by panitia.
     */
    private function buildQrDataUri(Employee $employee): string
    {
        return $this->ancolQrDataUri($this->qrCategory($employee));
    }

    private function ancolQrDataUri(string $category): string
    {
        $qrPath = storage_path('app/public/ancol-qr-' . $category . '.png');

        return file_exists($qrPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($qrPath))
            : '';
    }

    private function qrCategory(Employee $employee): string
    {
        if ($employee->transport_type === 'operational') {
            return 'operational';
        }

        return $employee->employee_type === 'expat' ? 'expat' : 'local';
    }
}
