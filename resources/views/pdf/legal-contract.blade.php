<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $contract->contract_number }}</title>
    <style>
        @page { margin: 24mm 18mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111111;
            line-height: 1.5;
        }
        .center { text-align: center; }
        .muted { color: #555555; }
        .header-title { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .header-subtitle { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .meta {
            margin-top: 8px;
            border: 1px solid #333333;
            padding: 6px 8px;
            font-size: 10px;
        }
        .line { border-top: 1px solid #333333; margin: 10px 0; }
        .party-title {
            font-weight: bold;
            background: #efefef;
            border: 1px solid #333333;
            padding: 5px 8px;
        }
        .party-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #333333;
            margin-bottom: 10px;
        }
        .party-table td {
            border: 1px solid #333333;
            padding: 5px 8px;
            vertical-align: top;
            font-size: 10.5px;
        }
        .party-label { width: 30%; }
        .pasal-title {
            font-weight: bold;
            margin-top: 12px;
            margin-bottom: 4px;
            font-size: 11px;
            text-transform: uppercase;
        }
        .pasal-content {
            text-align: justify;
            font-size: 10.5px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 12px;
        }
        .ttd-gap { height: 70px; }
        .footer-note {
            margin-top: 20px;
            font-size: 9px;
            color: #666666;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="center">
        <div class="header-title">{{ $contractTypeLabel }}</div>
        <div class="header-subtitle">{{ $contract->title }}</div>
        <div class="meta">
            <strong>Nomor:</strong> {{ $contract->contract_number }}
            &nbsp; | &nbsp;
            <strong>Tanggal:</strong> {{ $contract->contract_date->format('d-m-Y') }}
        </div>
    </div>

    <div class="line"></div>

    <p>
        Perjanjian ini dibuat dan ditandatangani oleh para pihak yang disebutkan di bawah ini, dengan itikad baik
        dan tanpa adanya paksaan dari pihak mana pun, dengan ketentuan sebagai berikut:
    </p>

    {{-- PIHAK PERTAMA --}}
    <div class="party-title">PIHAK PERTAMA (Penyedia Jasa)</div>
        <table class="party-table">
            <tr>
                <td class="party-label">Nama / Perusahaan</td>
                <td>:&nbsp; <strong>{{ $pihakPertama['name'] ?? '-' }}</strong></td>
            </tr>
            <tr>
                <td class="party-label">Alamat</td>
                <td>:&nbsp; {{ $pihakPertama['address'] ?: '-' }}</td>
            </tr>
            <tr>
                <td class="party-label">No. Telepon / WA</td>
                <td>:&nbsp; {{ $pihakPertama['phone'] ?: '-' }}</td>
            </tr>
            <tr>
                <td class="party-label">Email</td>
                <td>:&nbsp; {{ $pihakPertama['email'] ?: '-' }}</td>
            </tr>
            @if(!empty($pihakPertama['bank']))
            <tr>
                <td class="party-label">No. Rekening</td>
                <td>:&nbsp; {{ $pihakPertama['bank'] }}</td>
            </tr>
            @endif
        </table>

    {{-- PIHAK KEDUA --}}
    <div class="party-title">PIHAK KEDUA (Klien / Pemberi Kerja)</div>
        <table class="party-table">
            <tr>
                <td class="party-label">Nama / Perusahaan</td>
                <td>:&nbsp; <strong>{{ $pihakKedua['name'] ?? '-' }}</strong></td>
            </tr>
            <tr>
                <td class="party-label">Alamat</td>
                <td>:&nbsp; {{ $pihakKedua['address'] ?: '-' }}</td>
            </tr>
            <tr>
                <td class="party-label">No. Telepon / WA</td>
                <td>:&nbsp; {{ $pihakKedua['phone'] ?: '-' }}</td>
            </tr>
            <tr>
                <td class="party-label">Email</td>
                <td>:&nbsp; {{ $pihakKedua['email'] ?: '-' }}</td>
            </tr>
            @if(!empty($pihakKedua['pic']))
            <tr>
                <td class="party-label">PIC / Penanggung Jawab</td>
                <td>:&nbsp; {{ $pihakKedua['pic'] }}</td>
            </tr>
            @endif
        </table>

    <p class="muted" style="font-size:10px;">
        Pihak Pertama dan Pihak Kedua selanjutnya secara bersama-sama disebut sebagai &ldquo;Para Pihak&rdquo;,
        telah sepakat untuk mengadakan perjanjian ini dengan ketentuan-ketentuan sebagai berikut:
    </p>

    {{-- PASALS --}}
    @foreach($pasals as $pasal)
    <div>
        <div class="pasal-title">{{ $pasal['title'] }}</div>
        <div class="pasal-content">{!! nl2br(e($pasal['content'])) !!}</div>
    </div>
    @endforeach

    {{-- SIGNATURES --}}
    <div class="line"></div>
        <p class="center muted" style="font-size:10px;">
            Demikian Perjanjian ini dibuat dan ditandatangani oleh Para Pihak dengan penuh kesadaran,
            tanpa paksaan dari pihak manapun.
        </p>
        <table class="signature-table">
            <tr>
                <td>
                    <strong>PIHAK PERTAMA</strong><br>
                    <span class="muted">(Penyedia Jasa)</span>
                    <div class="ttd-gap"></div>
                    <div>_______________________________</div>
                    <div><strong>{{ $pihakPertama['name'] ?? '________________' }}</strong></div>
                    <div class="muted" style="font-size:9px;">Materai Rp 10.000,-</div>
                </td>
                <td>
                    <strong>PIHAK KEDUA</strong><br>
                    <span class="muted">(Klien / Pemberi Kerja)</span>
                    <div class="ttd-gap"></div>
                    <div>_______________________________</div>
                    <div><strong>{{ $pihakKedua['name'] ?? '________________' }}</strong></div>
                    <div class="muted" style="font-size:9px;">Materai Rp 10.000,-</div>
                </td>
            </tr>
        </table>

    <div class="footer-note">
        Dokumen ini digenerate secara otomatis oleh sistem &bull; Status: {{ strtoupper($contract->status) }} &bull; {{ now()->format('d/m/Y H:i') }}
    </div>

</body>
</html>
