<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Core\Models\Company;
use App\ERP\Core\Services\ErpCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ERPAccountingOpeningBalanceController extends Controller
{
    public function __construct(private readonly GlPostingService $glPostingService) {}

    public function index(Request $request): Response
    {
        $companyId = ErpCompanyResolver::resolveForReporting($request);

        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type', 'normal_balance']);

        $openingQuery = JournalEntry::query()
            ->with(['lines.account', 'company:id,name'])
            ->where('source_module', 'opening_balance');

        if ($companyId) {
            $openingQuery->where('company_id', $companyId);
        }

        $openingEntries = $openingQuery
            ->latest('entry_date')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'entry_no' => $entry->entry_no,
                'entry_date' => $entry->entry_date?->toDateString(),
                'description' => $entry->description,
                'source_reference' => $entry->source_reference,
                'company_name' => $entry->company?->name,
                'total_debit' => (float) $entry->lines->sum('debit'),
                'total_credit' => (float) $entry->lines->sum('credit'),
                'lines' => $entry->lines->map(fn ($line) => [
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ])->values(),
            ]);

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name', 'tax_id']);

        return Inertia::render('ERP/Accounting/OpeningBalance', [
            'accounts' => $accounts,
            'openingEntries' => $openingEntries,
            'companies' => $companies,
            'selected_company_id' => $companyId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['nullable', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
            'entry_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_active', true)],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $lines = collect($validated['lines'])
            ->map(function (array $line): array {
                $debit = round((float) ($line['debit'] ?? 0), 2);
                $credit = round((float) ($line['credit'] ?? 0), 2);

                if ($debit > 0 && $credit > 0) {
                    throw ValidationException::withMessages([
                        'lines' => 'Satu baris jurnal hanya boleh diisi debit atau kredit.',
                    ]);
                }

                return [
                    'account_id' => (int) $line['account_id'],
                    'description' => $line['description'] ?? null,
                    'debit' => $debit,
                    'credit' => $credit,
                ];
            })
            ->filter(fn (array $line) => $line['debit'] > 0 || $line['credit'] > 0)
            ->values();

        if ($lines->count() < 2) {
            throw ValidationException::withMessages([
                'lines' => 'Saldo awal membutuhkan minimal dua baris akun.',
            ]);
        }

        $totalDebit = round((float) $lines->sum('debit'), 2);
        $totalCredit = round((float) $lines->sum('credit'), 2);

        if ($totalDebit <= 0 || abs($totalDebit - $totalCredit) >= 0.01) {
            throw ValidationException::withMessages([
                'lines' => 'Total debit dan kredit saldo awal harus seimbang.',
            ]);
        }

        $entryDate = $validated['entry_date'];
        $companyId = ErpCompanyResolver::resolveForGlPosting($request);
        $description = trim((string) ($validated['description'] ?? '')) ?: 'Saldo awal per '.$entryDate;
        $sourceReference = 'OPENING-'.str_replace('-', '', $entryDate).'-'.now()->format('His');

        $this->glPostingService->post(
            $companyId,
            sourceModule: 'opening_balance',
            sourceReference: $sourceReference,
            description: $description,
            entryDate: $entryDate,
            lines: $lines->all(),
        );

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Saldo awal berhasil diposting ke General Ledger.',
        ]);
    }
}
