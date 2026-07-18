@php
    $showPrintDate = $config['show_print_date'] ?? true;
    $customText    = $config['text'] ?? '';
@endphp
<div style="margin-top:10px;font-size:8.5px;color:#6b7280;text-align:right;">
    @if ($customText) {{ $customText }}<br> @endif
    @if ($showPrintDate) Dicetak pada {{ $generatedAt->locale('id')->translatedFormat('d F Y H:i') }} WITA @endif
</div>
