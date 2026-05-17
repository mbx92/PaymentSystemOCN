<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PersonalModuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Personal/Index', [
            'menus' => [
                [
                    'title' => 'Ringkasan',
                    'description' => 'Gambaran pemasukan, pengeluaran, dan saldo pribadi/keluarga.',
                    'route' => 'personal.overview',
                    'icon' => 'chart-bar',
                ],
                [
                    'title' => 'Dompet',
                    'description' => 'Kelola rekening, tunai, e-wallet — tentukan alur uang Anda.',
                    'route' => 'personal.wallets',
                    'icon' => 'wallet',
                ],
                [
                    'title' => 'Master kategori',
                    'description' => 'Kategori pemasukan & pengeluaran untuk transaksi dan anggaran.',
                    'route' => 'personal.categories',
                    'icon' => 'tag',
                ],
                [
                    'title' => 'Pemasukan & pengeluaran',
                    'description' => 'Catat transaksi harian: gaji, tagihan, belanja, tabungan.',
                    'route' => 'personal.transactions',
                    'icon' => 'arrows-right-left',
                ],
                [
                    'title' => 'Anggaran keluarga',
                    'description' => 'Alokasi bulanan per kategori (makan, pendidikan, dll.).',
                    'route' => 'personal.budgets',
                    'icon' => 'clipboard-list',
                ],
                [
                    'title' => 'Investasi',
                    'description' => 'Catat instrumen, setoran, penarikan, dan dividen.',
                    'route' => 'personal.investments',
                    'icon' => 'arrow-trending-up',
                ],
            ],
        ]);
    }
}
