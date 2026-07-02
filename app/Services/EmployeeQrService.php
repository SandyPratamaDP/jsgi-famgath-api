<?php

namespace App\Services;

use App\Models\Employee;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class EmployeeQrService
{
    private const CAPTION = 'QR untuk verifikasi masuk wahana Sea World & Samudera Ancol';

    public function renderPng(Employee $employee): string
    {
        return $this->composeCard($employee, $this->renderQrOnly($employee));
    }

    public function filename(Employee $employee): string
    {
        $safeName = preg_replace('/[^a-z0-9]+/', '_', strtolower($employee->name));
        return sprintf('%s_qr.png', $safeName);
    }

    private function renderQrOnly(Employee $employee): string
    {
        $builder = new Builder(
            writer: new PngWriter(),
            data: $employee->qr_code,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 480,
            margin: 20,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        return $builder->build()->getString();
    }

    /**
     * Compose the raw QR into a printable card: famgath logo, a caption explaining
     * what the code is for, the QR itself, and the employee name + short manual_code
     * printed as text underneath (manual-entry fallback if the scan fails). The long
     * qr_code itself is never printed — it's only meant to be read by a scanner.
     */
    private function composeCard(Employee $employee, string $qrPngBytes): string
    {
        $font = __DIR__ . '/../../vendor/endroid/qr-code/assets/open_sans.ttf';

        $qrImage = imagecreatefromstring($qrPngBytes);
        $qrWidth  = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);

        $paddingX  = 40;
        $canvasWidth = $qrWidth + $paddingX * 2;

        $logo = $this->loadLogo();
        $logoHeight = $logo ? 130 : 0;
        $logoWidth  = $logo ? (int) round(imagesx($logo) / imagesy($logo) * $logoHeight) : 0;

        $captionLines = $this->wrapText($font, 15, self::CAPTION, $canvasWidth - $paddingX * 2);

        $topPadding    = 32;
        $bottomPadding = 32;
        $lineHeight    = 22;

        $canvasHeight = $topPadding
            + ($logo ? $logoHeight + 20 : 0)
            + count($captionLines) * $lineHeight + 20
            + $qrHeight + 28
            + 1 + 20 // divider + gap
            + $lineHeight + 6 // employee name
            + 16 + 30 // "kode manual" hint + code (larger font)
            + $bottomPadding;

        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);

        $dark  = imagecolorallocate($canvas, 15, 23, 42);
        $muted = imagecolorallocate($canvas, 100, 116, 139);
        $line  = imagecolorallocate($canvas, 226, 232, 240);

        $y = $topPadding;

        if ($logo) {
            imagecopyresampled(
                $canvas, $logo,
                (int) (($canvasWidth - $logoWidth) / 2), $y,
                0, 0,
                $logoWidth, $logoHeight,
                imagesx($logo), imagesy($logo)
            );
            $y += $logoHeight + 20;
        }

        foreach ($captionLines as $captionLine) {
            $this->drawCenteredText($canvas, $font, 15, $muted, $canvasWidth, $y, $captionLine);
            $y += $lineHeight;
        }
        $y += 20;

        imagecopy($canvas, $qrImage, $paddingX, $y, 0, 0, $qrWidth, $qrHeight);
        $y += $qrHeight + 28;

        imageline($canvas, $paddingX, $y, $canvasWidth - $paddingX, $y, $line);
        $y += 20;

        $this->drawCenteredText($canvas, $font, 17, $dark, $canvasWidth, $y, $employee->name, bold: true);
        $y += $lineHeight + 6;

        $this->drawCenteredText($canvas, $font, 11, $muted, $canvasWidth, $y, 'Kode manual jika QR gagal dipindai:');
        $y += 16;

        $this->drawCenteredText($canvas, $font, 22, $dark, $canvasWidth, $y, $this->formatManualCode($employee->manual_code), bold: true);

        ob_start();
        imagepng($canvas);
        $bytes = ob_get_clean();

        imagedestroy($canvas);
        imagedestroy($qrImage);
        if ($logo) {
            imagedestroy($logo);
        }

        return $bytes;
    }

    private function formatManualCode(string $code): string
    {
        return implode('-', str_split($code, 4));
    }

    private function loadLogo(): \GdImage|false|null
    {
        $path = storage_path('app/public/logo.webp');
        if (!file_exists($path) || !function_exists('imagecreatefromwebp')) {
            return null;
        }

        $logo = @imagecreatefromwebp($path);
        return $logo ?: null;
    }

    /** @return string[] */
    private function wrapText(string $font, int $size, string $text, int $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($this->textWidth($font, $size, $candidate) > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function textWidth(string $font, int $size, string $text): int
    {
        $box = imagettfbbox($size, 0, $font, $text);
        return abs($box[4] - $box[0]);
    }

    private function drawCenteredText(\GdImage $canvas, string $font, int $size, int $color, int $canvasWidth, int $y, string $text, bool $bold = false): void
    {
        $width = $this->textWidth($font, $size, $text);
        $x = (int) (($canvasWidth - $width) / 2);

        imagettftext($canvas, $size, 0, $x, $y + $size, $color, $font, $text);
        if ($bold) {
            imagettftext($canvas, $size, 0, $x + 1, $y + $size, $color, $font, $text);
        }
    }
}
