<?php

namespace App\Services;

use Carbon\Carbon;

class InvoiceService
{
    public function getInvoiceDocument(string|int $id): array
    {
        $invoice = $this->dummyInvoice((string) $id);
        $items = collect($invoice['items'])->map(function (array $item, int $index) {
            $qty = (float) $item['qty'];
            $price = (float) $item['price'];

            return $item + [
                'no' => $index + 1,
                'subtotal' => $qty * $price,
            ];
        })->all();

        $subtotal = (float) collect($items)->sum('subtotal');
        $taxAmount = 0;
        $total = $subtotal;

        return [
            'invoice' => $invoice + [
                'items' => $items,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'formatted_date' => $this->formatDateIndo($invoice['date']),
                'formatted_due_date' => $this->formatDateIndo($invoice['due_date']),
                'status_label' => $this->statusLabel($invoice['status']),
                'status_color' => $this->statusColor($invoice['status']),
            ],
            'companyLogoPath' => file_exists(public_path('images/logo.png'))
                ? public_path('images/logo.png')
                : null,
            'generatedAt' => now(),
        ];
    }

    private function dummyInvoice(string $id): array
    {
        $sequence = str_pad((string) preg_replace('/\D+/', '', $id), 4, '0', STR_PAD_LEFT);
        $sequence = $sequence === '0000' ? '0042' : $sequence;

        return [
            'number' => 'INV-2025-'.$sequence,
            'date' => '2025-05-23',
            'due_date' => '2025-06-06',
            'company' => [
                'name' => 'PT. Nama Perusahaan',
                'address' => 'Jl. Sunset Road No. 88, Kuta, Bali',
                'phone' => '+62 812-3456-7890',
                'email' => 'info@perusahaan.co.id',
                'npwp' => '12.345.678.9-012.000',
            ],
            'client' => [
                'name' => 'CV. Klien Bahagia',
                'address' => 'Jl. Raya Seminyak No. 20, Badung, Bali',
                'phone' => '+62 877-0000-1111',
                'email' => 'klien@example.com',
            ],
            'items' => [
                ['description' => 'Jasa Pembuatan Website', 'qty' => 1, 'unit' => 'paket', 'price' => 5000000],
                ['description' => 'Setup Hosting & Domain (1 Tahun)', 'qty' => 1, 'unit' => 'tahun', 'price' => 750000],
                ['description' => 'Revisi Desain', 'qty' => 3, 'unit' => 'sesi', 'price' => 200000],
            ],
            'tax_percent' => 11,
            'notes' => 'Pembayaran dapat dilakukan melalui transfer BCA: 1234567890 a/n PT. Nama Perusahaan.',
            'status' => 'unpaid',
        ];
    }

    private function formatDateIndo(string $date): string
    {
        return Carbon::parse($date)
            ->locale('id')
            ->translatedFormat('d F Y');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'paid' => 'LUNAS',
            'overdue' => 'JATUH TEMPO',
            default => 'BELUM DIBAYAR',
        };
    }

    private function statusColor(string $status): string
    {
        return match ($status) {
            'paid' => '#166534',
            'overdue' => '#991b1b',
            default => '#92400e',
        };
    }
}
