<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice Project</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.5;">
    <p>Halo {{ $recipientName }},</p>

    <p>
        Berikut kami kirimkan invoice project dengan nomor
        <strong>{{ $invoice['number'] ?? '-' }}</strong>.
    </p>

    <ul>
        <li>Project: {{ $project['name'] ?? '-' }}</li>
        <li>Client: {{ $project['client_name'] ?? '-' }}</li>
        <li>Nilai invoice: Rp {{ number_format((float) ($invoice['amount'] ?? 0), 0, ',', '.') }}</li>
        <li>Sisa tagihan: Rp {{ number_format((float) ($invoice['remaining_amount'] ?? 0), 0, ',', '.') }}</li>
    </ul>

    <p>File PDF invoice terlampir pada email ini.</p>

    <p>Terima kasih.</p>

    {{-- Text fallback for email clients that don't render HTML --}}
    @section('text')
Halo {{ $recipientName }},

Berikut kami kirimkan invoice project dengan nomor {{ $invoice['number'] ?? '-' }}.

- Project: {{ $project['name'] ?? '-' }}
- Client: {{ $project['client_name'] ?? '-' }}
- Nilai invoice: Rp {{ number_format((float) ($invoice['amount'] ?? 0), 0, ',', '.') }}
- Sisa tagihan: Rp {{ number_format((float) ($invoice['remaining_amount'] ?? 0), 0, ',', '.') }}

File PDF invoice terlampir pada email ini.

Terima kasih.
    @endsection
</body>
</html>
