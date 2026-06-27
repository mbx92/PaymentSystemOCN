@php
    $showNo        = $config['show_no'] ?? true;
    $showUom       = $config['show_uom'] ?? true;
    $showUnitPrice = $config['show_unit_price'] ?? true;
    $accentColor   = $config['accent_color'] ?? ($settings['accent_color'] ?? '#1E3A5F');
    $items         = $items ?? collect();
@endphp
<div class="doc-section-title" style="color:{{ $accentColor }};">Item</div>
<table class="inner-table">
    <thead>
        <tr>
            @if ($showNo) <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};width:7%;">No</th> @endif
            <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};">Deskripsi</th>
            <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};width:10%;">Qty</th>
            @if ($showUom) <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};width:11%;">Satuan</th> @endif
            @if ($showUnitPrice) <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};width:16%;">Harga</th> @endif
            <th style="background:{{ $accentColor }};color:#fff;padding:6px 5px;font-size:8.5px;text-transform:uppercase;letter-spacing:.8px;border:1px solid {{ $accentColor }};width:18%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $index => $item)
            <tr style="{{ $index % 2 === 1 ? 'background:#f9fafb;' : 'background:#fff;' }}">
                @if ($showNo) <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;text-align:center;">{{ $index + 1 }}</td> @endif
                <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;">{{ $item['name'] }}</td>
                <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;text-align:center;">{{ number_format((float) $item['qty'], 0, ',', '.') }}</td>
                @if ($showUom) <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;text-align:center;">{{ $item['uom'] ?? 'unit' }}</td> @endif
                @if ($showUnitPrice) <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;text-align:right;">{{ $rupiah($item['unit_price']) }}</td> @endif
                <td style="padding:5px;border:1px solid #e5e7eb;font-size:9px;text-align:right;">{{ $rupiah($item['subtotal']) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="padding:5px;border:1px solid #e5e7eb;font-size:9px;text-align:center;">Belum ada item.</td></tr>
        @endforelse
    </tbody>
</table>
