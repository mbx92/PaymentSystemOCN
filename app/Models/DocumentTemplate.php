<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentTemplate extends Model
{
    protected $fillable = ['name', 'type', 'is_active', 'blocks', 'settings'];

    protected $casts = [
        'is_active' => 'boolean',
        'blocks' => 'array',
        'settings' => 'array',
    ];

    public static function activeFor(string $type): ?self
    {
        return self::query()->where('type', $type)->where('is_active', true)->first();
    }

    public static function defaultInvoiceBlocks(): array
    {
        return [
            ['id' => 'header',       'type' => 'header',       'enabled' => true,  'config' => ['show_logo' => true, 'show_tagline' => true, 'accent_color' => '#1E3A5F']],
            ['id' => 'client_info',  'type' => 'client_info',  'enabled' => true,  'config' => ['label' => 'Customer', 'show_contact' => true]],
            ['id' => 'items_table',  'type' => 'items_table',  'enabled' => true,  'config' => ['show_no' => true, 'show_uom' => true, 'show_unit_price' => true]],
            ['id' => 'totals',       'type' => 'totals',       'enabled' => true,  'config' => ['show_subtotal' => true, 'show_tax' => false, 'show_discount' => true, 'show_paid' => true, 'show_remaining' => true]],
            ['id' => 'notes',        'type' => 'notes',        'enabled' => true,  'config' => ['text' => 'Cantumkan nomor invoice pada berita transfer.']],
            ['id' => 'signature',    'type' => 'signature',    'enabled' => true,  'config' => ['label' => 'Hormat kami,', 'name_placeholder' => 'Finance']],
            ['id' => 'footer',       'type' => 'footer',       'enabled' => true,  'config' => ['show_print_date' => true]],
        ];
    }

    public static function defaultSalesNoteBlocks(): array
    {
        return [
            ['id' => 'header',       'type' => 'header',       'enabled' => true,  'config' => ['show_logo' => true, 'show_tagline' => true, 'accent_color' => '#1E3A5F', 'title' => 'NOTA PENJUALAN', 'subtitle' => 'Lampiran Item Penjualan']],
            ['id' => 'client_info',  'type' => 'client_info',  'enabled' => true,  'config' => ['label' => 'Customer', 'show_contact' => true]],
            ['id' => 'items_table',  'type' => 'items_table',  'enabled' => true,  'config' => ['show_no' => true, 'show_uom' => true, 'show_unit_price' => true]],
            ['id' => 'totals',       'type' => 'totals',       'enabled' => true,  'config' => ['show_subtotal' => true, 'show_tax' => false, 'show_discount' => true, 'label_total' => 'Total Dokumen']],
            ['id' => 'signature',    'type' => 'signature',    'enabled' => true,  'config' => ['label' => 'Disiapkan oleh,', 'name_placeholder' => '']],
            ['id' => 'footer',       'type' => 'footer',       'enabled' => true,  'config' => ['show_print_date' => true]],
        ];
    }

    public static function defaultPosReceiptBlocks(): array
    {
        return [
            ['id' => 'store_header',      'type' => 'store_header',      'enabled' => true, 'config' => ['show_address' => true, 'show_phone' => true]],
            ['id' => 'transaction_info',  'type' => 'transaction_info',  'enabled' => true, 'config' => ['show_cashier' => true, 'show_channel' => true]],
            ['id' => 'items',             'type' => 'items',             'enabled' => true, 'config' => ['show_sku' => false]],
            ['id' => 'totals',            'type' => 'totals',            'enabled' => true, 'config' => ['show_discount' => true, 'show_tax' => false, 'show_change' => true]],
            ['id' => 'payment_info',      'type' => 'payment_info',      'enabled' => true, 'config' => ['show_method' => true]],
            ['id' => 'footer_message',    'type' => 'footer_message',    'enabled' => true, 'config' => ['text' => 'Terima kasih atas kunjungan Anda!']],
        ];
    }
}
