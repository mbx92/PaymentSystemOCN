@php
    $showNo        = $config['show_no'] ?? true;
    $showUom       = $config['show_uom'] ?? true;
    $showUnitPrice = $config['show_unit_price'] ?? true;
    $accentColor   = $config['accent_color'] ?? ($settings['accent_color'] ?? config('pdf.theme.primary', '#1d4ed8'));
    $items         = $items ?? collect();
@endphp
<div class="doc-section-title" style="color:{{ $accentColor }};">Item</div>
<table class="inner-table items-table" style="margin-top: 0;">
    <thead>
        <tr>
            @if ($showNo) <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};width:7%;">No</th> @endif
            <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};">Deskripsi</th>
            <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};width:10%;">Qty</th>
            @if ($showUom) <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};width:11%;">Satuan</th> @endif
            @if ($showUnitPrice) <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};width:16%;">Harga</th> @endif
            <th style="background:{{ $accentColor }};border-color:{{ $accentColor }};width:18%;">Subtotal</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($items as $index => $item)
            <tr>
                @if ($showNo) <td class="text-center">{{ $index + 1 }}</td> @endif
                <td>{{ $item['name'] }}</td>
                <td class="text-center">{{ number_format((float) $item['qty'], 0, ',', '.') }}</td>
                @if ($showUom) <td class="text-center">{{ $item['uom'] ?? 'unit' }}</td> @endif
                @if ($showUnitPrice) <td class="text-right">{{ $rupiah($item['unit_price']) }}</td> @endif
                <td class="text-right">{{ $rupiah($item['subtotal']) }}</td>
            </tr>
        @empty
            <tr class="empty-row"><td colspan="6">Belum ada item.</td></tr>
        @endforelse
    </tbody>
</table>
