<?php

namespace Database\Seeders;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class OpeningBalanceSeeder extends Seeder
{
    /**
     * Contoh jurnal saldo awal (debit Kas + Bank BCA + Piutang, kredit Modal Pemilik).
     * Idempotent: tidak menggandakan jika sudah pernah di-seed (source_reference tetap).
     */
    private const SOURCE_REFERENCE = 'OPENING-SEED-DEMO';

    public function run(): void
    {
        $companyId = (int) (Company::query()->where('is_active', true)->orderBy('id')->value('id') ?? 0);
        if (! $companyId) {
            $this->command?->warn('OpeningBalanceSeeder: tidak ada perusahaan aktif, lewati.');

            return;
        }

        if (JournalEntry::query()->where('source_reference', self::SOURCE_REFERENCE)->where('company_id', $companyId)->exists()) {
            $this->command?->info('Opening balance demo sudah ada, lewati OpeningBalanceSeeder.');

            return;
        }

        $lines = [];
        $push = function (string $code, float $debit, float $credit, ?string $lineNote = null) use (&$lines): void {
            $account = Account::query()->where('code', $code)->where('is_active', true)->first();
            if (! $account) {
                $this->command?->warn("Akun {$code} tidak ditemukan atau nonaktif; baris diabaikan.");

                return;
            }
            $lines[] = [
                'account_id' => $account->id,
                'debit' => $debit,
                'credit' => $credit,
                'description' => $lineNote,
            ];
        };

        // Contoh neraca pembuka (nilai fiktif, IDR)
        $push('1001', 5_000_000, 0, 'Saldo awal kas tunai');
        $push('1002', 25_000_000, 0, 'Saldo awal rekening Bank BCA');
        $push('1101', 2_000_000, 0, 'Saldo awal piutang usaha');
        $push('3001', 0, 32_000_000, 'Setoran modal pemilik (penyeimbang saldo awal)');

        $lines = array_values(array_filter($lines, fn (array $l) => $l['debit'] > 0 || $l['credit'] > 0));

        $totalDebit = round(array_sum(array_column($lines, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($lines, 'credit')), 2);

        if (count($lines) < 2 || $totalDebit <= 0 || abs($totalDebit - $totalCredit) >= 0.01) {
            $this->command?->warn('OpeningBalanceSeeder: baris tidak seimbang atau akun kurang — tidak memposting.');

            return;
        }

        $user = User::query()->first();
        if ($user) {
            Auth::login($user);
        }

        $entryDate = now()->startOfYear()->toDateString();

        app(GlPostingService::class)->post(
            $companyId,
            sourceModule: 'opening_balance',
            sourceReference: self::SOURCE_REFERENCE,
            description: 'Saldo awal demo dari seeder (Kas, Bank BCA, Piutang vs Modal Pemilik)',
            entryDate: $entryDate,
            lines: $lines,
        );

        $this->command?->info('Saldo awal demo berhasil diposting ke GL.');
    }
}
