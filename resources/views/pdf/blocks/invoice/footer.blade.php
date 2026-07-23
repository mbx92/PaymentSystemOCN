@php
    $showPrintDate = $config['show_print_date'] ?? true;
    $customText    = $config['text'] ?? '';
@endphp
<div class="footer-note">
    @if ($customText) {{ $customText }}<br> @endif
    @if ($showPrintDate) Dicetak pada {{ $generatedAt->locale('id')->translatedFormat('d F Y H:i') }} WITA @endif
</div>
