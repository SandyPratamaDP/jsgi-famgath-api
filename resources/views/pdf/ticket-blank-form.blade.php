<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Tiket Kosong - Kendaraan Pribadi</title>
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
        .strip { height: 5px; border-radius: 12px 12px 0 0; background: #0284c7; }

        /* ── Header ── */
        .header { background: #0f172a; padding: 14px 20px; }
        .header-title { color: #ffffff; font-size: 15px; font-weight: bold; }
        .header-sub   { color: #94a3b8; font-size: 9px; margin-top: 3px; }

        .badge {
            font-size: 11px; font-weight: bold;
            padding: 6px 16px; border-radius: 20px; color: #ffffff;
            background: #0284c7;
        }

        /* ── Name section ── */
        .name-section { padding: 16px 20px 12px; border-bottom: 1px solid #f1f5f9; }
        .name-label {
            font-size: 10px; color: #1e293b;
            text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px;
        }
        .blank-line {
            border-bottom: 1.5px dashed #94a3b8;
            height: 22px;
        }

        /* ── Stat boxes (stacked, left column) ── */
        .stat-box {
            background: #f8fafc; border: 1px solid #e2e8f0;
            border-radius: 8px; padding: 12px 14px; margin-bottom: 10px;
        }
        .stat-box:last-child { margin-bottom: 0; }

        .stat-label {
            font-size: 10px; color: #1e293b;
            text-transform: uppercase; letter-spacing: 0.8px;
        }

        .stat-blank-line {
            border-bottom: 1.5px dashed #94a3b8;
            height: 28px; margin-top: 6px; width: 70%;
        }
        .stat-unit { font-size: 11px; color: #94a3b8; margin-top: 3px; }

        /* ── QR cell (right column) ── */
        .qr-wrap {
            background: #ffffff; border: 1px solid #e2e8f0;
            border-radius: 10px; padding: 8px;
        }
        .qr-caption {
            font-size: 11px; color: #1e293b; text-align: center; margin-top: 7px;
        }

        /* ── Divider ── */
        .divider { border-top: 1px solid #e2e8f0; margin: 14px 0 10px; }

        /* ── Footer ── */
        .footer {
            background: #f8fafc; border-top: 1px solid #e2e8f0;
            padding: 9px 20px; border-radius: 0 0 12px 12px;
            font-size: 11px; color: #1e293b;
        }
        .footer-bold { color: #0f172a; font-weight: bold; }
    </style>
</head>
<body>
<div class="page">
    <div class="card">

        <div class="strip"></div>

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
                        <span class="badge">Kendaraan Pribadi</span>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Name (blank, filled by hand) --}}
        <div class="name-section">
            <div class="name-label">Nama Karyawan</div>
            <div class="blank-line"></div>
        </div>

        {{-- Two-column: left = blank stats stacked, right = large QR --}}
        <div style="padding: 14px 20px 16px;">
            <table width="100%" style="border-collapse:collapse;">
                <tr>

                    <td style="vertical-align:top; padding-right:16px;">

                        <div class="stat-box">
                            <div class="stat-label">Jumlah Orang</div>
                            <div class="stat-blank-line"></div>
                            <div class="stat-unit">orang</div>
                        </div>

                        <div class="stat-box">
                            <div class="stat-label">Jumlah Kendaraan</div>
                            <div class="stat-blank-line"></div>
                            <div class="stat-unit">unit</div>
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

            <div style="font-size:10px; color:#1e293b; font-style:italic;">
                Kartu ini merupakan tiket masuk resmi ke kawasan Ancol untuk keperluan
                Family Gathering JSGI 2026. Harap dijaga dan tunjukkan kepada petugas
                saat memasuki area.
            </div>
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
</div>
</body>
</html>
