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
    public function renderPng(Employee $employee): string
    {
        $builder = new Builder(
            writer: new PngWriter(),
            data: $employee->qr_code,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 600,
            margin: 20,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );
        $result = $builder->build();

        return $result->getString();
    }

    public function filename(Employee $employee): string
    {
        $safeName = preg_replace('/[^a-z0-9]+/', '_', strtolower($employee->name));
        return sprintf('%s_qr.png', $safeName);
    }
}
