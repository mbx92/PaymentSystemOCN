@php
    $showSubtotal  = $config['show_subtotal'] ?? true;
    $showTax       = $config['show_tax'] ?? false;
    $showDiscount  = $config['show_discount'] ?? true;
    $showPaid      = $config['show_paid'] ?? false;
    $showRemaining = $config['show_remaining'] ?? false;
    $labelTotal    = $config['label_total'] ?? 'Total';
    $accentColor   = $config['accent_color'] ?? ($settings['accent_color'] ?? config('pdf.theme.primary', '#1d4ed8'));

    $subtotalVal  = $itemsSubtotal ?? 0;
    $taxVal       = $taxAmount ?? 0;
    $discountVal  = $totalDiscount ?? 0;
    $cashVal      = $cashReceived ?? 0;
    $invoiceAmt   = $invoice['amount'] ?? 0;
    $paidAmt      = $invoice['paid_amount'] ?? 0;
    $remainingAmt = $invoice['remaining_amount'] ?? 0;
@endphp
<table>
    <tr>
        <td></td>
        <td style="width:84mm;">
            <div class="doc-section-title" style="color:{{ $accentColor }};">Ringkasan</div>
            <table class="summary-box" style="width: 100%; margin-left: 0;">
                @if ($showSubtotal)
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="value">{{ $rupiah($subtotalVal) }}</td>
                    </tr>
                @endif
                @if ($showTax)
                    <tr>
                        <td class="label">PPN</td>
                        <td class="value">{{ $rupiah($taxVal) }}</td>
                    </tr>
                @endif
                @if ($showDiscount && $discountVal > 0)
                    <tr>
                        <td class="label">Total Tagihan</td>
                        <td class="value">{{ $rupiah($invoiceAmt) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Diskon (Potongan)</td>
                        <td class="value">− {{ $rupiah($discountVal) }}</td>
                    </tr>
                    <tr>
                        <td class="label">Jumlah Bayar (Kas)</td>
                        <td class="value">{{ $rupiah($cashVal) }}</td>
                    </tr>
                @endif
                @if ($showPaid)
                    <tr>
                        <td class="label">Sudah Dibayar</td>
                        <td class="value">{{ $rupiah($paidAmt) }}</td>
                    </tr>
                @endif
                @if ($showRemaining)
                    <tr>
                        <td class="label">Sisa Tagihan</td>
                        <td class="value">{{ $rupiah($remainingAmt) }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:7px 10px;background:{{ $accentColor }};color:#fff;font-size:10px;font-weight:700;border-bottom:0;">{{ $labelTotal }}</td>
                    <td style="padding:7px 10px;background:{{ $accentColor }};color:#fff;font-size:10px;font-weight:700;text-align:right;border-bottom:0;">{{ $rupiah($invoiceAmt) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
