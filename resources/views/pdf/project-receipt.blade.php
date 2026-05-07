<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Kwitansi {{ $invoice['number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 24px; margin: 0; }
        .muted { color: #6b7280; }
        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header > div { display: table-cell; vertical-align: top; }
        .right { text-align: right; }
        .box { border: 1px solid #d1d5db; border-radius: 8px; padding: 14px; margin-top: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; text-align: left; }
        th { background: #f3f4f6; width: 30%; }
        .amount { font-size: 20px; font-weight: bold; }
        .signature { margin-top: 54px; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>KWITANSI</h1>
            <div class="muted">Invoice: {{ $invoice['number'] }}</div>
        </div>
        <div class="right">
            <strong>Tanggal Cetak</strong><br>
            {{ $generatedAt->format('Y-m-d H:i') }}
        </div>
    </div>

    <div class="box">
        <table>
            <tr><th>Telah diterima dari</th><td>{{ $project->client_name }}</td></tr>
            <tr><th>Untuk pembayaran</th><td>Invoice project {{ $project->name }}</td></tr>
            <tr><th>Tanggal Bayar</th><td>{{ $cashIn->date?->format('Y-m-d') ?: '-' }}</td></tr>
            <tr><th>Metode Bayar</th><td>{{ $cashIn->paymentMethod?->name ?: '-' }}</td></tr>
            <tr><th>Metode/Keterangan</th><td>{{ $cashIn->note ?: '-' }}</td></tr>
            <tr><th>Jumlah</th><td class="amount">Rp {{ number_format((float) $cashIn->amount, 0, ',', '.') }}</td></tr>
        </table>
    </div>

    <div class="signature">
        <div>Hormat kami,</div>
        <br><br><br>
        <strong>Finance</strong>
    </div>
</body>
</html>
