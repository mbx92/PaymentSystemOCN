<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\JournalEntry;
use App\ERP\Core\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ERPAccountingUtilityController extends Controller
{
    public function index(Request $request): Response
    {
        $companies = Company::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name', 'is_active']);

        $query = JournalEntry::query()
            ->with('company:id,name')
            ->withSum('lines as debit_total', 'debit')
            ->withSum('lines as credit_total', 'credit')
            ->latest('entry_date')
            ->latest('id');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('date_from')) {
            $query->where('entry_date', '>=', $request->string('date_from')->toString());
        }

        if ($request->filled('date_to')) {
            $query->where('entry_date', '<=', $request->string('date_to')->toString());
        }

        if ($request->filled('q')) {
            $term = $request->string('q')->toString();
            $query->where(function ($inner) use ($term): void {
                $inner->where('entry_no', 'like', '%'.$term.'%')
                    ->orWhere('description', 'like', '%'.$term.'%')
                    ->orWhere('source_module', 'like', '%'.$term.'%');
            });
        }

        $entries = $query
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'entry_no' => $entry->entry_no,
                'entry_date' => $entry->entry_date?->format('Y-m-d'),
                'description' => $entry->description,
                'source_module' => $entry->source_module,
                'source_reference' => $entry->source_reference,
                'company_id' => $entry->company_id,
                'company_name' => $entry->company?->name ?? 'Belum ditentukan',
                'debit_total' => (float) ($entry->debit_total ?? 0),
                'credit_total' => (float) ($entry->credit_total ?? 0),
            ]);

        $companySummaries = JournalEntry::query()
            ->leftJoin('companies', 'companies.id', '=', 'journal_entries.company_id')
            ->selectRaw('COALESCE(companies.name, ?) as company_name', ['Belum ditentukan'])
            ->selectRaw('journal_entries.company_id')
            ->selectRaw('COUNT(*) as entry_count')
            ->groupBy('journal_entries.company_id', 'companies.name')
            ->orderBy('companies.name')
            ->get()
            ->map(fn ($row) => [
                'company_id' => $row->company_id,
                'company_name' => $row->company_name,
                'entry_count' => (int) $row->entry_count,
            ]);

        return Inertia::render('ERP/Accounting/Utilities', [
            'companies' => $companies,
            'entries' => $entries,
            'companySummaries' => $companySummaries,
            'filters' => $this->filtersWithPerPage($request, ['company_id', 'date_from', 'date_to', 'q']),
        ]);
    }

    public function moveJournalEntries(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_company_id' => ['required', 'integer', 'exists:companies,id'],
            'journal_entry_ids' => ['required', 'array', 'min:1'],
            'journal_entry_ids.*' => ['integer', 'exists:journal_entries,id'],
        ]);

        $targetCompanyId = (int) $validated['target_company_id'];
        $journalEntryIds = collect($validated['journal_entry_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $movedCount = DB::transaction(function () use ($journalEntryIds, $targetCompanyId): int {
            return JournalEntry::query()
                ->whereIn('id', $journalEntryIds)
                ->where(function ($query) use ($targetCompanyId): void {
                    $query->where('company_id', '!=', $targetCompanyId)
                        ->orWhereNull('company_id');
                })
                ->update(['company_id' => $targetCompanyId]);
        });

        $target = Company::query()->find($targetCompanyId);

        return back()->with('flash', [
            'type' => 'success',
            'message' => $movedCount.' transaksi accounting dipindahkan ke '.($target?->name ?? 'usaha tujuan').'.',
        ]);
    }
}
