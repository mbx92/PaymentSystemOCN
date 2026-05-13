<?php

namespace Database\Seeders;

use App\ERP\Accounting\Models\Account;
use App\Models\CashCategory;
use App\Models\CategoryCoaMapping;
use Illuminate\Database\Seeder;

class CoaSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // Assets (1xxx)
            ['code' => '1001', 'name' => 'Kas', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1002', 'name' => 'Bank BCA', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1003', 'name' => 'Bank Mandiri', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1004', 'name' => 'Kas Kecil (Petty Cash)', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1101', 'name' => 'Piutang Usaha', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1102', 'name' => 'Piutang Karyawan', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1201', 'name' => 'Persediaan Barang Dagang', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1202', 'name' => 'Persediaan Bahan Baku', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1301', 'name' => 'Sewa Dibayar Dimuka', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1302', 'name' => 'Asuransi Dibayar Dimuka', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1401', 'name' => 'Peralatan', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1402', 'name' => 'Kendaraan', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1403', 'name' => 'Akumulasi Penyusutan Peralatan', 'type' => 'asset', 'normal_balance' => 'credit'],
            ['code' => '1404', 'name' => 'Akumulasi Penyusutan Kendaraan', 'type' => 'asset', 'normal_balance' => 'credit'],

            // Liabilities (2xxx)
            ['code' => '2001', 'name' => 'Hutang Usaha', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2002', 'name' => 'Hutang Pajak', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2003', 'name' => 'Hutang Gaji', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2004', 'name' => 'Hutang Bank', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2005', 'name' => 'Pendapatan Diterima Dimuka', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '2006', 'name' => 'Dana Titipan Material Client', 'type' => 'liability', 'normal_balance' => 'credit'],

            // Equity (3xxx)
            ['code' => '3001', 'name' => 'Modal Pemilik', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '3002', 'name' => 'Laba Ditahan', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '3003', 'name' => 'Prive Pemilik', 'type' => 'equity', 'normal_balance' => 'debit'],

            // Revenue (4xxx)
            ['code' => '4001', 'name' => 'Pendapatan Jasa', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4002', 'name' => 'Pendapatan Penjualan POS', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4003', 'name' => 'Pendapatan Project', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4004', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue', 'normal_balance' => 'credit'],
            ['code' => '4005', 'name' => 'Diskon Penjualan', 'type' => 'revenue', 'normal_balance' => 'debit'],

            // Expenses (5xxx)
            ['code' => '5001', 'name' => 'Beban Operasional', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5002', 'name' => 'Beban Gaji & Upah', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5003', 'name' => 'Beban Sewa', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5004', 'name' => 'Beban Listrik & Air', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5005', 'name' => 'Beban Telepon & Internet', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5006', 'name' => 'Beban Transportasi', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5007', 'name' => 'Beban ATK', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5008', 'name' => 'Beban Perlengkapan', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5009', 'name' => 'HPP - Harga Pokok Penjualan', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5010', 'name' => 'Beban Penyusutan', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5011', 'name' => 'Beban Asuransi', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5012', 'name' => 'Beban Pajak', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5013', 'name' => 'Beban Lain-lain', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5014', 'name' => 'Beban Marketing & Iklan', 'type' => 'expense', 'normal_balance' => 'debit'],
            ['code' => '5015', 'name' => 'Beban Maintenance', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];

        foreach ($accounts as $account) {
            Account::query()->firstOrCreate(
                ['code' => $account['code']],
                $account
            );
        }

        $cashCategories = [
            // Cash-in
            ['domain' => 'cash_in', 'key' => 'pendapatan_project', 'label' => 'Pendapatan Project', 'sort_order' => 10],
            ['domain' => 'cash_in', 'key' => 'uang_muka_project', 'label' => 'Uang Muka Project', 'sort_order' => 12],
            ['domain' => 'cash_in', 'key' => 'dana_material_client', 'label' => 'Dana Material dari Client', 'sort_order' => 14],
            ['domain' => 'cash_in', 'key' => 'pendapatan_pos', 'label' => 'Pendapatan Penjualan POS', 'sort_order' => 20],
            ['domain' => 'cash_in', 'key' => 'pendapatan_jasa', 'label' => 'Pendapatan Jasa', 'sort_order' => 30],
            ['domain' => 'cash_in', 'key' => 'piutang_masuk', 'label' => 'Penerimaan Piutang', 'sort_order' => 40],
            ['domain' => 'cash_in', 'key' => 'investasi_masuk', 'label' => 'Investasi / Setoran Modal', 'sort_order' => 50],
            ['domain' => 'cash_in', 'key' => 'pendapatan_lainnya', 'label' => 'Pendapatan Lain-lain', 'sort_order' => 60],
            ['domain' => 'cash_in', 'key' => 'refund_pembelian', 'label' => 'Refund Pembelian', 'sort_order' => 70],

            // Cash-out
            ['domain' => 'cash_out', 'key' => 'operasional', 'label' => 'Biaya Operasional', 'sort_order' => 10],
            ['domain' => 'cash_out', 'key' => 'gaji_karyawan', 'label' => 'Gaji Karyawan', 'sort_order' => 20],
            ['domain' => 'cash_out', 'key' => 'pembelian_material_project', 'label' => 'Pembelian Material Project', 'sort_order' => 25],
            ['domain' => 'cash_out', 'key' => 'pemakaian_dana_material_client', 'label' => 'Pemakaian Dana Material Client', 'sort_order' => 26],
            ['domain' => 'cash_out', 'key' => 'pembelian_bahan', 'label' => 'Pembelian Bahan / Barang', 'sort_order' => 30],
            ['domain' => 'cash_out', 'key' => 'sewa_tempat', 'label' => 'Sewa Tempat', 'sort_order' => 40],
            ['domain' => 'cash_out', 'key' => 'listrik_air', 'label' => 'Listrik & Air', 'sort_order' => 50],
            ['domain' => 'cash_out', 'key' => 'transportasi', 'label' => 'Transportasi', 'sort_order' => 60],
            ['domain' => 'cash_out', 'key' => 'marketing', 'label' => 'Marketing & Iklan', 'sort_order' => 70],
            ['domain' => 'cash_out', 'key' => 'pajak', 'label' => 'Pajak', 'sort_order' => 80],
            ['domain' => 'cash_out', 'key' => 'pinjaman', 'label' => 'Pembayaran Pinjaman', 'sort_order' => 90],
            ['domain' => 'cash_out', 'key' => 'pengeluaran_lainnya', 'label' => 'Pengeluaran Lain-lain', 'sort_order' => 100],
            ['domain' => 'cash_out', 'key' => 'refund_penjualan_pos', 'label' => 'Refund Penjualan POS', 'sort_order' => 110],
        ];

        foreach ($cashCategories as $cat) {
            CashCategory::query()->firstOrCreate(
                ['domain' => $cat['domain'], 'key' => $cat['key']],
                $cat
            );
        }

        $coaMappings = [
            // Cash-in → Account
            ['domain' => 'cash_in', 'category' => 'pendapatan_project', 'account_code' => '4003'],
            ['domain' => 'cash_in', 'category' => 'uang_muka_project', 'account_code' => '2005'],
            ['domain' => 'cash_in', 'category' => 'dana_material_client', 'account_code' => '2006'],
            ['domain' => 'cash_in', 'category' => 'pendapatan_pos', 'account_code' => '4002'],
            ['domain' => 'cash_in', 'category' => 'pendapatan_jasa', 'account_code' => '4001'],
            ['domain' => 'cash_in', 'category' => 'piutang_masuk', 'account_code' => '1101'],
            ['domain' => 'cash_in', 'category' => 'investasi_masuk', 'account_code' => '3001'],
            ['domain' => 'cash_in', 'category' => 'pendapatan_lainnya', 'account_code' => '4004'],
            ['domain' => 'cash_in', 'category' => 'refund_pembelian', 'account_code' => '2001'],

            // Cash-out → Account
            ['domain' => 'cash_out', 'category' => 'operasional', 'account_code' => '5001'],
            ['domain' => 'cash_out', 'category' => 'gaji_karyawan', 'account_code' => '5002'],
            ['domain' => 'cash_out', 'category' => 'pembelian_material_project', 'account_code' => '5009'],
            ['domain' => 'cash_out', 'category' => 'pemakaian_dana_material_client', 'account_code' => '2006'],
            ['domain' => 'cash_out', 'category' => 'pembelian_bahan', 'account_code' => '5009'],
            ['domain' => 'cash_out', 'category' => 'sewa_tempat', 'account_code' => '5003'],
            ['domain' => 'cash_out', 'category' => 'listrik_air', 'account_code' => '5004'],
            ['domain' => 'cash_out', 'category' => 'transportasi', 'account_code' => '5006'],
            ['domain' => 'cash_out', 'category' => 'marketing', 'account_code' => '5014'],
            ['domain' => 'cash_out', 'category' => 'pajak', 'account_code' => '5012'],
            ['domain' => 'cash_out', 'category' => 'pinjaman', 'account_code' => '2004'],
            ['domain' => 'cash_out', 'category' => 'pengeluaran_lainnya', 'account_code' => '5013'],
            ['domain' => 'cash_out', 'category' => 'refund_penjualan_pos', 'account_code' => '4005'],
        ];

        foreach ($coaMappings as $mapping) {
            $account = Account::query()->where('code', $mapping['account_code'])->first();
            if ($account) {
                CategoryCoaMapping::query()->firstOrCreate(
                    ['domain' => $mapping['domain'], 'category' => $mapping['category']],
                    ['account_id' => $account->id]
                );
            }
        }
    }
}
