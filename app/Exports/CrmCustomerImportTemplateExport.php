<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CrmCustomerImportTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'code',
            'name',
            'company',
            'email',
            'phone',
            'address',
            'business_type',
            'tax_id',
            'source',
            'pic_email',
            'pic_name',
            'is_active',
            'notes',
        ];
    }

    public function array(): array
    {
        return [[
            'CUST-0101',
            'Budi Santoso',
            'PT Contoh Integrasi',
            'budi@contoh.test',
            '081234567890',
            'Jl. Ahmad Yani No. 10, Makassar',
            'retail',
            '01.234.567.8-999.000',
            'import_excel',
            'admin@ocn.test',
            'Administrator',
            '1',
            'Baris contoh. Hapus atau ganti dengan data customer Anda.',
        ]];
    }
}
