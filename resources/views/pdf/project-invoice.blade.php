<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $invoice['number'] }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; background: #f3f6fb; }
        .page { width: 100%; min-height: 100%; background: #ffffff; padding: 54px 68px 0; }
        .head { width: 100%; border-collapse: collapse; }
        .head td { border: 0; padding: 0; vertical-align: top; }
        .title { margin: 28px 0 10px; color: #1d4ed8; font-size: 48px; font-weight: 300; letter-spacing: 0.18em; }
        .brand-box { width: 184px; height: 184px; margin-left: auto; background: #1d4ed8; color: #ffffff; text-align: center; padding: 24px 16px; }
        .logo-img { width: 76px; height: 76px; object-fit: contain; margin: 4px auto 18px; display: block; }
        .logo-fallback { width: 76px; height: 76px; margin: 4px auto 18px; border: 5px solid #ffffff; border-radius: 18px; line-height: 66px; font-weight: 800; font-size: 22px; }
        .brand-name { font-size: 15px; font-weight: 800; letter-spacing: 0.16em; text-transform: uppercase; }
        .brand-tagline { margin-top: 7px; font-size: 9px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: #dbeafe; }
        .label { font-size: 12px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; }
        .muted { color: #111827; line-height: 1.55; }
        .date-grid { width: 290px; margin-top: 8px; border-collapse: collapse; }
        .date-grid td { border: 0; padding: 0 26px 0 0; }
        .section-grid { width: 100%; margin-top: 30px; border-collapse: collapse; }
        .section-grid td { width: 50%; border: 0; padding: 0; vertical-align: top; }
        .section-title { margin: 0 0 10px; font-size: 16px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; }
        .info-table td { border: 0; padding: 3px 0; }
        .info-table td:first-child { width: 92px; color: #111827; }
        .items-title { margin: 36px 0 8px; font-size: 16px; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; }
        .items { width: 100%; border-collapse: collapse; }
        .items th { padding: 9px 4px; border-top: 1px solid #b8c0cc; border-bottom: 1px solid #b8c0cc; text-align: left; font-size: 12px; font-weight: 800; letter-spacing: 0.03em; text-transform: uppercase; }
        .items td { padding: 9px 4px; border-bottom: 1px solid #c7cdd6; }
        .text-right { text-align: right !important; }
        .totals-wrap { width: 100%; margin-top: 18px; border-collapse: collapse; }
        .totals-wrap td { border: 0; padding: 0; vertical-align: top; }
        .totals { width: 275px; margin-left: auto; border-collapse: collapse; }
        .totals td { padding: 6px 0; border: 0; font-weight: 800; }
        .totals .divider td { border-top: 1px solid #b8c0cc; padding-top: 13px; }
        .bottom { width: 100%; margin-top: 26px; border-collapse: collapse; }
        .bottom td { width: 50%; border: 0; padding: 0; vertical-align: top; }
        .bottom td:first-child { padding-right: 32px; }
        .note-title { margin-bottom: 9px; font-size: 14px; font-weight: 800; color: #1d4ed8; letter-spacing: 0.03em; }
        .thanks { margin-top: 34px; font-weight: 800; font-style: italic; line-height: 1.7; letter-spacing: 0.02em; }
        .footer { position: fixed; left: 0; right: 0; bottom: 0; height: 66px; background: #e2e8f0; padding: 22px 74px; font-size: 11px; font-weight: 800; }
        .footer span { display: inline-block; margin-right: 46px; }
    </style>
</head>
<body>
@php
    $brand = $brand ?? ['name' => config('app.name', 'OCN ERP Suite'), 'tagline' => 'Integrated Business Platform', 'logo_data_uri' => null];
    $printedAt = $generatedAt->format('F d, Y');
    $dueDate = $generatedAt->copy()->addDays(14)->format('F d, Y');
    $terms = $project->payments->isNotEmpty()
        ? $project->payments->map(fn ($term) => [
            'name' => 'Termin '.$term->term_number.' - '.number_format((float) $term->percentage, 2, ',', '.').'%',
            'qty' => 1,
            'unit_price' => (float) $term->amount,
            'subtotal' => (float) $term->amount,
            'note' => $term->note,
        ])
        : collect([[
            'name' => 'Total nilai project '.$project->name,
            'qty' => 1,
            'unit_price' => (float) $invoice['amount'],
            'subtotal' => (float) $invoice['amount'],
            'note' => $project->description,
        ]]);
    $termSubtotal = (float) $terms->sum('subtotal');
@endphp
<div class="page">
    <table class="head">
        <tr>
            <td>
                <h1 class="title">INVOICE</h1>
                <div class="label">Invoice Number</div>
                <div>{{ $invoice['number'] }}</div>
                <table class="date-grid">
                    <tr>
                        <td><div class="label">Date</div><div>{{ $printedAt }}</div></td>
                        <td><div class="label">Due Date</div><div>{{ $dueDate }}</div></td>
                    </tr>
                </table>
            </td>
            <td style="width: 210px;">
                <div class="brand-box">
                    @if($brand['logo_data_uri'])
                        <img class="logo-img" src="{{ $brand['logo_data_uri'] }}" alt="Logo">
                    @else
                        <div class="logo-fallback">OCN</div>
                    @endif
                    <div class="brand-name">{{ $brand['name'] }}</div>
                    <div class="brand-tagline">{{ $brand['tagline'] }}</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="section-grid">
        <tr>
            <td>
                <h2 class="section-title">To :</h2>
                <div class="muted">
                    {{ $project->client_name }}<br>
                    {{ $project->client_contact ?: '-' }}<br>
                    {{ $project->name }}
                </div>
            </td>
            <td>
                <h2 class="section-title">Project Information</h2>
                <table class="info-table">
                    <tr><td>Project</td><td>: {{ $project->name }}</td></tr>
                    <tr><td>Type</td><td>: {{ $project->project_type ?: '-' }}</td></tr>
                    <tr><td>Finished</td><td>: {{ $project->finished_at?->format('F d, Y') ?: '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <h2 class="items-title">Termin Description</h2>
    <table class="items">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-right" style="width: 12%;">Qty</th>
                <th class="text-right" style="width: 24%;">Unit Price</th>
                <th class="text-right" style="width: 24%;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($terms as $term)
                <tr>
                    <td>{{ $term['name'] }}{{ $term['note'] ? ' - '.$term['note'] : '' }}</td>
                    <td class="text-right">{{ number_format((float) $term['qty'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format((float) $term['unit_price'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format((float) $term['subtotal'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrap">
        <tr>
            <td></td>
            <td style="width: 310px;">
                <table class="totals">
                    <tr><td>SUBTOTAL</td><td>:</td><td class="text-right">Rp {{ number_format($termSubtotal, 0, ',', '.') }}</td></tr>
                    <tr><td>PAID</td><td>:</td><td class="text-right">Rp {{ number_format((float) $invoice['paid_amount'], 0, ',', '.') }}</td></tr>
                    <tr class="divider"><td>TOTAL DUE</td><td>:</td><td class="text-right">Rp {{ number_format((float) $invoice['remaining_amount'], 0, ',', '.') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="bottom">
        <tr>
            <td>
                <h2 class="section-title">Payment Terms</h2>
                <p class="muted">
                    Payment is due within 14 days from invoice date.<br>
                    Please include invoice number {{ $invoice['number'] }} in the payment description.
                </p>
            </td>
            <td>
                <div class="note-title">Notes</div>
                <p class="muted">
                    Detail item tersedia pada Nota Penjualan. Hubungi finance jika ada pertanyaan terkait invoice ini.
                </p>
            </td>
        </tr>
    </table>

    <div class="thanks">THANK YOU FOR YOUR BUSINESS! WE LOOK FORWARD<br>TO FUTURE OPPORTUNITIES.</div>
</div>
<div class="footer">
    <span>{{ $brand['name'] }}</span>
    <span>{{ $brand['tagline'] }}</span>
</div>
</body>
</html>
