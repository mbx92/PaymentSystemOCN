<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 24px; margin: 0; }
        h2 { font-size: 14px; margin: 0 0 8px 0; }
        .muted { color: #6b7280; }
        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header > div { display: table-cell; vertical-align: top; }
        .right { text-align: right; }
        .box { border: 1px solid #d1d5db; border-radius: 8px; padding: 12px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .text-right { text-align: right; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>INVOICE</h1>
            <div class="muted">{{ $invoice['number'] }}</div>
        </div>
        <div class="right">
            <strong>Tanggal Cetak</strong><br>
            {{ $generatedAt->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="box">
        <h2>Informasi Project</h2>
        <table>
            <tr><th style="width: 30%">Project</th><td>{{ $project->name }}</td></tr>
            <tr><th>Client</th><td>{{ $project->client_name }}</td></tr>
            <tr><th>Kontak</th><td>{{ $project->client_contact ?: '-' }}</td></tr>
            <tr><th>Tanggal Selesai</th><td>{{ $project->finished_at?->format('Y-m-d') ?: '-' }}</td></tr>
            <tr><th>Deskripsi</th><td>{{ $project->description ?: '-' }}</td></tr>
        </table>
    </div>

    <div class="box">
        <h2>Tagihan</h2>
        <table>
            <thead>
                <tr>
                    <th>Deskripsi</th>
                    <th class="text-right" style="width: 25%">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Nilai project {{ $project->name }}</td>
                    <td class="text-right">Rp {{ number_format((float) $invoice['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="text-right total">Total Invoice</td>
                    <td class="text-right total">Rp {{ number_format((float) $invoice['amount'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="text-right">Terbayar</td>
                    <td class="text-right">Rp {{ number_format((float) $invoice['paid_amount'], 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td class="text-right total">Sisa Tagihan</td>
                    <td class="text-right total">Rp {{ number_format((float) $invoice['remaining_amount'], 0, ',', '.') }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
