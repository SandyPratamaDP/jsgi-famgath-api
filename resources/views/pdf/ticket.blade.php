<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kartu - {{ $employee->name }}</title>
    <style>
        * { margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, sans-serif;
            background: #eef2f7;
            color: #0f172a;
            font-size: 12px;
        }

        .page {
            width: 170mm;
            margin: 10mm auto;
        }

        /* ── Card ── */
        .card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
        }

        /* ── Accent strip ── */
        .strip { height: 5px; border-radius: 12px 12px 0 0; }
        .strip-car { background: #0284c7; }
        .strip-bus { background: #7c3aed; }
        .strip-operational { background: #0d9488; }
        .strip-additional { background: #ea580c; }

        /* ── Header ── */
        .header { background: #0f172a; padding: 14px 20px; }
        .header-title { color: #ffffff; font-size: 15px; font-weight: bold; }
        .header-sub   { color: #94a3b8; font-size: 9px; margin-top: 3px; }

        .badge {
            font-size: 11px; font-weight: bold;
            padding: 6px 16px; border-radius: 20px; color: #ffffff;
        }
        .badge-car { background: #0284c7; }
        .badge-bus { background: #7c3aed; }
        .badge-operational { background: #0d9488; }
        .badge-additional { background: #ea580c; }

        /* ── Name section ── */
        .name-section { padding: 16px 20px 12px; border-bottom: 1px solid #f1f5f9; }
        .name-label {
            font-size: 8px; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px;
        }
        .name-value {
            font-size: 21px; font-weight: bold; color: #0f172a; line-height: 1.2;
        }

        /* ── PIC tag ── */
        .pic-tag {
            font-size: 8px; font-weight: bold; color: #ffffff;
            background: #7c3aed; padding: 2px 9px;
            border-radius: 5px; vertical-align: middle;
        }

        /* ── Stat boxes (stacked, left column) ── */
        .stat-box {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 8px; padding: 12px 14px; margin-bottom: 10px;
        }
        .stat-box-bus {
            background: #faf5ff; border: 1px solid #ddd6fe;
            border-radius: 8px; padding: 12px 14px; margin-bottom: 10px;
        }
        .stat-box:last-child, .stat-box-bus:last-child { margin-bottom: 0; }

        .stat-label {
            font-size: 8px; color: #94a3b8;
            text-transform: uppercase; letter-spacing: 0.8px;
        }
        .stat-label-bus { font-size: 8px; color: #a78bfa; text-transform: uppercase; letter-spacing: 0.8px; }

        .stat-number {
            font-size: 32px; font-weight: bold; line-height: 1.1; margin-top: 4px;
        }
        .stat-unit { font-size: 13px; font-weight: normal; color: #64748b; }

        .stat-text { font-size: 18px; font-weight: bold; line-height: 1.2; margin-top: 4px; }

        .color-blue { color: #0ea5e9; }
        .color-car  { color: #0284c7; }
        .color-bus  { color: #7c3aed; }

        /* ── QR cell (right column) ── */
        .qr-wrap {
            background: #ffffff; border: 1px solid #e2e8f0;
            border-radius: 10px; padding: 8px;
        }
        .qr-caption {
            font-size: 8.5px; color: #94a3b8; text-align: center; margin-top: 7px;
        }

        /* ── Divider ── */
        .divider { border-top: 1px solid #e2e8f0; margin: 14px 0 10px; }

        /* ── Footer ── */
        .footer {
            background: #f8fafc; border-top: 1px solid #e2e8f0;
            padding: 9px 20px; border-radius: 0 0 12px 12px;
            font-size: 8.5px; color: #94a3b8;
        }
        .footer-bold { color: #0f172a; font-weight: bold; }

        .card-additional { margin-top: 10mm; }
    </style>
</head>
<body>
<div class="page">
    <div class="card">

        {{-- Accent strip --}}
        <div class="strip
            @if($employee->transport_type === 'private_car') strip-car
            @elseif($employee->transport_type === 'operational') strip-operational
            @else strip-bus
            @endif
        "></div>

        {{-- Header --}}
        <div class="header">
            <table width="100%" style="border-collapse:collapse;">
                <tr>
                    <td width="58" style="vertical-align:middle;">
                        <img src="{{ $logoData }}" width="48" height="48"
                             style="display:block; border-radius:6px;" alt="Logo" />
                    </td>
                    <td style="vertical-align:middle; padding-left:12px;">
                        <div class="header-title">Family Gathering JSGI 2026</div>
                        <div class="header-sub">PT. JFE Steel Galvanizing Indonesia</div>
                    </td>
                    <td style="vertical-align:middle; text-align:right; white-space:nowrap;">
                        @if($employee->transport_type === 'private_car')
                            <span class="badge badge-car">Kendaraan Pribadi</span>
                        @elseif($employee->transport_type === 'operational')
                            <span class="badge badge-operational">Operational</span>
                        @else
                            <span class="badge badge-bus">Bus {{ $employee->bus_number }}</span>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        {{-- Name (full width) --}}
        <div class="name-section">
            <div class="name-label">Nama Karyawan</div>
            <div class="name-value">
                {{ $employee->name }}
                @if($employee->is_pic_bus)
                    &nbsp;<span class="pic-tag">* PIC BUS</span>
                @endif
            </div>
        </div>

        {{-- Two-column: left = stats stacked, right = large QR --}}
        <div style="padding: 14px 20px 16px;">
            <table width="100%" style="border-collapse:collapse;">
                <tr>

                    {{-- Left column: stacked boxes --}}
                    <td style="vertical-align:top; padding-right:16px;">

                        {{-- Jumlah Orang --}}
                        <div class="stat-box">
                            <div class="stat-label">Jumlah Orang</div>
                            <div class="stat-number color-blue">
                                @if($employee->is_pic_bus)
                                    {{ $employee->total_bus_passengers ?? 0 }}
                                @else
                                    {{ $employee->total_passengers ?? 1 }}
                                @endif
                                <span class="stat-unit">orang</span>
                            </div>
                        </div>

                        @if($employee->transport_type === 'private_car' || $employee->transport_type === 'operational')
                            {{-- Jumlah Kendaraan --}}
                            <div class="stat-box">
                                <div class="stat-label">Jumlah Kendaraan</div>
                                <div class="stat-number color-car">
                                    {{ $employee->total_vehicles ?? 1 }}
                                    <span class="stat-unit">unit</span>
                                </div>
                            </div>
                        @else
                            {{-- Titik Jemputan --}}
                            <div class="stat-box-bus">
                                <div class="stat-label-bus">Titik Jemputan</div>
                                <div class="stat-text color-bus">{{ $employee->pickup_point ?? '-' }}</div>
                            </div>
                        @endif

                    </td>

                    {{-- Right column: large QR --}}
                    <td width="178" style="vertical-align:middle; text-align:center;">
                        <div class="qr-wrap">
                            <img src="{{ $qrData }}" width="160" height="160" alt="QR Ancol" />
                        </div>
                        <div class="qr-caption">QR Masuk Ancol</div>
                    </td>

                </tr>
            </table>

            <div class="divider"></div>

            {{-- Disclaimer --}}
            <div style="font-size:8px; color:#94a3b8; font-style:italic;">
                Kartu ini merupakan tiket masuk resmi ke kawasan Ancol untuk keperluan
                Family Gathering JSGI 2026. Harap dijaga dan tunjukkan kepada petugas
                saat memasuki area.
            </div>

            @if($employee->has_below_two_children ?? false)
                <div style="margin-top:8px; background:#fffbeb; border:1px solid #fbbf24; border-radius:6px; padding:6px 10px; font-size:9px; font-weight:bold; color:#92400e;">
                    PENTING: Anak di bawah 2 tahun tidak dihitung dalam jumlah orang dan tidak perlu membayar tiket.
                </div>
            @endif

            @if($employee->transport_type === 'operational')
                <div style="margin-top:8px; background:#f0fdfa; border:1px solid #0d9488; border-radius:6px; padding:6px 10px; font-size:9px; font-weight:bold; color:#0f766e;">
                    PENTING: Tiket ini berlaku untuk KELUAR-MASUK kawasan Ancol BERKALI-KALI selama masa berlaku.
                </div>
            @endif
        </div>

        {{-- Footer --}}
        <div class="footer">
            <table width="100%" style="border-collapse:collapse;">
                <tr>
                    <td>Family Gathering JSGI 2026 &middot; Ancol, Jakarta</td>
                    <td style="text-align:right; white-space:nowrap;">
                        Dicetak: {{ now()->format('d M Y') }}
                        &nbsp;&nbsp;
                        <span class="footer-bold">Berlaku 11 Juli 2026</span>
                    </td>
                </tr>
            </table>
        </div>

    </div>

    {{-- Additional ticket: guests outside the employee's core family --}}
    @if(($employee->additional_members ?? 0) > 0)
        <div class="card card-additional">

            <div class="strip strip-additional"></div>

            <div class="header">
                <table width="100%" style="border-collapse:collapse;">
                    <tr>
                        <td width="58" style="vertical-align:middle;">
                            <img src="{{ $logoData }}" width="48" height="48"
                                 style="display:block; border-radius:6px;" alt="Logo" />
                        </td>
                        <td style="vertical-align:middle; padding-left:12px;">
                            <div class="header-title">Family Gathering JSGI 2026</div>
                            <div class="header-sub">PT. JFE Steel Galvanizing Indonesia</div>
                        </td>
                        <td style="vertical-align:middle; text-align:right; white-space:nowrap;">
                            <span class="badge badge-additional">Tiket Tambahan</span>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="name-section">
                <div class="name-label">Nama Karyawan</div>
                <div class="name-value">{{ $employee->name }}</div>
            </div>

            <div style="padding: 14px 20px 16px;">
                <table width="100%" style="border-collapse:collapse;">
                    <tr>

                        <td style="vertical-align:top; padding-right:16px;">

                            {{-- Jumlah Orang (additional guests only) --}}
                            <div class="stat-box">
                                <div class="stat-label">Jumlah Orang</div>
                                <div class="stat-number color-blue">
                                    {{ $employee->additional_members }}
                                    <span class="stat-unit">orang</span>
                                </div>
                            </div>

                            {{-- Additional guests always arrange & pay for their own vehicle --}}
                            <div class="stat-box">
                                <div class="stat-label">Jumlah Kendaraan</div>
                                <div class="stat-number color-car">
                                    0
                                    <span class="stat-unit">unit</span>
                                </div>
                            </div>

                        </td>

                        <td width="178" style="vertical-align:middle; text-align:center;">
                            <div class="qr-wrap">
                                <img src="{{ $qrData }}" width="160" height="160" alt="QR Ancol" />
                            </div>
                            <div class="qr-caption">QR Masuk Ancol</div>
                        </td>

                    </tr>
                </table>

                <div class="divider"></div>

                <div style="font-size:8px; color:#94a3b8; font-style:italic;">
                    Tiket tambahan untuk peserta rekreasi di luar keluarga inti karyawan
                    (bundle Seaworld, Ancol, dan Samudra). Kendaraan peserta tambahan
                    diatur dan dibayar secara terpisah.
                </div>
            </div>

            <div class="footer">
                <table width="100%" style="border-collapse:collapse;">
                    <tr>
                        <td>Family Gathering JSGI 2026 &middot; Ancol, Jakarta</td>
                        <td style="text-align:right; white-space:nowrap;">
                            Dicetak: {{ now()->format('d M Y') }}
                            &nbsp;&nbsp;
                            <span class="footer-bold">Berlaku 11 Juli 2026</span>
                        </td>
                    </tr>
                </table>
            </div>

        </div>
    @endif

</div>
</body>
</html>
