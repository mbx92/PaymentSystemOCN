@php
    $showSubtotal  = $config['show_subtotal'] ?? true;
    $showTax       = $config['show_tax'] ?? false;
    $showDiscount  = $config['show_discount'] ?? true;
    $showPaid      = $config['show_paid'] ?? false;
    $showRemaining = $config['show_remaining'] ?? false;
    $labelTotal    = $config['label_total'] ?? 'Total';
    $accentColor   = $config['accent_color'] ?? ($settings['accent_color'] ?? '#1E3A5F');

    $subtotalVal  = $itemsSubtotal ?? 0;
    $taxVal       = $taxAmount ?? 0;
    $discountVal  = $totalDiscount ?? 0;
    $cashVal      = $cashReceived ?? 0;
    $invoiceAmt   = $invoice['amount'] ?? 0;
    $paidAmt      = $invoice['paid_amount'] ?? 0;
    $remainingAmt = $invoice['remaining_amount'] ?? 0;
@endphp
<table style="width:100%;border-collapse:collapse;">
    <tr>
        <td></td>
        <td style="width:84mm;">
            <div class="doc-section-title" style="color:{{ $accentColor }};">Ringkasan</div>
            <table style="width:100%;border-collapse:collapse;border:1px solid #dbe4ee;background:#fff;">
                @if ($showSubtotal)
                    <tr>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;">Subtotal</td>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;text-align:right;">{{ $rupiah($subtotalVal) }}</td>
                    </tr>
                @endif
                @if ($showTax)
                    <tr>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;">PPN</td>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;text-align:right;">{{ $rupiah($taxVal) }}</td>
                    </tr>
                @endif
                @if ($showDiscount && $discountVal > 0)
                    <tr>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;">Total Tagihan</td>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;text-align:right;">{{ $rupiah($invoiceAmt) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;">Diskon (Potongan)</td>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;text-align:right;">− {{ $rupiah($discountVal) }}</td>
                    </tr>
                    <tr>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;">Jumlah Bayar (Kas)</td>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;text-align:right;">{{ $rupiah($cashVal) }}</td>
                    </tr>
                @endif
                @if ($showPaid)
                    <tr>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;">Sudah Dibayar</td>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;text-align:right;">{{ $rupiah($paidAmt) }}</td>
                    </tr>
                @endif
                @if ($showRemaining)
                    <tr>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;">Sisa Tagihan</td>
                        <td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px;text-align:right;">{{ $rupiah($remainingAmt) }}</td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:6px 8px;background:{{ $accentColor }};color:#fff;font-size:10px;font-weight:700;">{{ $labelTotal }}</td>
                    <td style="padding:6px 8px;background:{{ $accentColor }};color:#fff;font-size:10px;font-weight:700;text-align:right;">{{ $rupiah($invoiceAmt) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
