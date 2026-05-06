<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Budget</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        h1 { font-size: 20px; margin: 0 0 8px 0; }
        .muted { color: #6b7280; }
        .section { margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .text-right { text-align: right; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Budget Project</h1>
    <div class="muted">Generated: {{ $generatedAt->format('Y-m-d H:i') }}</div>

    <div class="section">
        <table>
            <tr><th style="width: 30%">Nama Project</th><td>{{ $budget->name }}</td></tr>
            <tr><th>Nama Klien</th><td>{{ $budget->client_name }}</td></tr>
            <tr><th>Kontak Klien</th><td>{{ $budget->client_contact ?: '-' }}</td></tr>
            <tr><th>Tipe Project</th><td>{{ $budget->project_type === 'cctv_installation' ? 'CCTV Installation' : 'System/Website Development' }}</td></tr>
            <tr><th>Status Budget</th><td>{{ strtoupper($budget->status) }}</td></tr>
            <tr><th>Estimasi Nilai</th><td>Rp {{ number_format((float) $budget->estimated_value, 0, ',', '.') }}</td></tr>
            <tr><th>Deskripsi</th><td>{{ $budget->description ?: '-' }}</td></tr>
        </table>
    </div>

    @if($budget->project_type === 'cctv_installation')
        <div class="section">
            <strong>Item CCTV</strong>
            <table>
                <thead>
                    <tr>
                        <th style="width: 50%">Produk</th>
                        <th style="width: 15%">Qty</th>
                        <th style="width: 17%">Harga Satuan</th>
                        <th style="width: 18%">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        @php
                            $qty = (float) ($item['qty'] ?? 0);
                            $unitPrice = (float) ($item['unit_price'] ?? 0);
                            $subtotal = $qty * $unitPrice;
                        @endphp
                        <tr>
                            <td>{{ $item['name'] ?? '-' }}</td>
                            <td class="text-right">{{ number_format($qty, 2, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($unitPrice, 0, ',', '.') }}</td>
                            <td class="text-right">Rp {{ number_format($subtotal, 0, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-right">Tidak ada item.</td></tr>
                    @endforelse
                    <tr>
                        <td colspan="3" class="text-right total">Total Item</td>
                        <td class="text-right total">Rp {{ number_format((float) $budget->estimated_value, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>

