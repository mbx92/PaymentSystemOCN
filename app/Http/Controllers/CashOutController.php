<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Accounting\Services\GlPostingService;
use App\ERP\Shared\Enums\DocumentStatus;
use App\Models\CashOut;
use App\Models\CategoryCoaMapping;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CashOutController extends Controller
{
    public function __construct(private readonly GlPostingService $glPostingService) {}

    public function index(Request $request)
    {
        $query = CashOut::with('project', 'creator')
            ->when(
                $request->filled('project_id') && Str::isUuid($request->project_id),
                fn ($q) => $q->where('project_id', $request->project_id)
            )
            ->when($request->category, fn ($q) => $q->where('category', $request->category))
            ->when($request->date_from, fn ($q) => $q->whereDate('date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('date', '<=', $request->date_to));

        $total = (float) (clone $query)->sum('amount');

        $cashOuts = $query->latest('date')->paginate($this->resolvedPerPage($request))->withQueryString()
            ->through(fn ($c) => [
                'id' => $c->id,
                'project_id' => $c->project_id,
                'cash_account_id' => $c->cash_account_id,
                'project_name' => $c->project->name,
                'category' => $c->category,
                'amount' => (float) $c->amount,
                'date' => $c->date->format('Y-m-d'),
                'note' => $c->note,
                'recipient_name' => $c->recipient_name,
                'creator_name' => $c->creator->name,
                'document_status' => $c->document_status,
                'journal_entry_id' => $c->journal_entry_id,
            ]);

        $projects = Project::orderBy('name')->get(['id', 'name']);

        return Inertia::render('CashOut/Index', [
            'cashOuts' => $cashOuts,
            'total' => $total,
            'projects' => $projects,
            'cashAccounts' => Account::query()
                ->where('is_active', true)
                ->where('type', 'asset')
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'filters' => $this->filtersWithPerPage($request, ['project_id', 'category', 'date_from', 'date_to']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'cash_account_id' => 'required|exists:accounts,id',
            'category' => 'required|in:biaya_tim,komisi_referral,operasional,lainnya',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'recipient_name' => 'nullable|string|max:255',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['document_status'] = DocumentStatus::Posted->value;
        $validated['approved_at'] = now();
        $validated['approved_by'] = Auth::id();
        $validated['posted_at'] = now();
        $validated['posted_by'] = Auth::id();
        $expenseAccountId = CategoryCoaMapping::query()
            ->where('domain', 'cash_out')
            ->where('category', $validated['category'])
            ->value('account_id');
        if (! $expenseAccountId) {
            return back()->withErrors(['category' => 'Kategori kas keluar belum di-mapping ke akun CoA.'])->withInput();
        }
        $cashOut = CashOut::create($validated);
        $expenseAccount = Account::query()->findOrFail($expenseAccountId);
        $cashAccount = Account::query()->findOrFail((int) $validated['cash_account_id']);

        $entry = $this->glPostingService->post(
            sourceModule: 'cash_out',
            sourceReference: (string) $cashOut->id,
            description: 'Kas keluar proyek '.$cashOut->project_id,
            entryDate: $validated['date'],
            lines: [
                ['account_id' => $expenseAccount->id, 'debit' => $validated['amount'], 'credit' => 0],
                ['account_id' => $cashAccount->id, 'debit' => 0, 'credit' => $validated['amount']],
            ]
        );

        $cashOut->update(['journal_entry_id' => $entry->id]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil ditambahkan.']);
    }

    public function update(Request $request, CashOut $cashOut)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|uuid|exists:projects,id',
            'cash_account_id' => 'required|exists:accounts,id',
            'category' => 'required|in:biaya_tim,komisi_referral,operasional,lainnya',
            'amount' => 'required|numeric|min:1',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'recipient_name' => 'nullable|string|max:255',
        ]);

        $cashOut->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil diperbarui.']);
    }

    public function destroy(CashOut $cashOut)
    {
        $cashOut->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Kas keluar berhasil dihapus.']);
    }
}
