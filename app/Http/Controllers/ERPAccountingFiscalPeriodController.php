<?php

namespace App\Http\Controllers;

use App\ERP\Core\Models\Company;
use App\ERP\Core\Models\FiscalPeriod;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\ERP\Core\Services\FiscalPeriodService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ERPAccountingFiscalPeriodController extends Controller
{
    public function __construct(private readonly FiscalPeriodService $fiscalPeriodService) {}

    public function index(Request $request): Response
    {
        $selectedYear = (int) $request->integer('year', now()->year);
        $selectedCompanyId = $this->selectedCompanyId($request);

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name']);

        return Inertia::render('ERP/Accounting/FiscalPeriods', [
            'companies' => $companies,
            'selected_company_id' => $selectedCompanyId,
            'selected_year' => $selectedYear,
            'periods' => $selectedCompanyId
                ? $this->fiscalPeriodService->periodRowsForYear($selectedCompanyId, $selectedYear)
                : ['yearly' => null, 'monthly' => []],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
            'period_type' => ['required', Rule::in([FiscalPeriodService::TYPE_MONTHLY, FiscalPeriodService::TYPE_YEARLY])],
            'period_year' => ['required', 'integer', 'between:2000,2100'],
            'period_month' => ['nullable', 'integer', 'between:1,12'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->fiscalPeriodService->close(
            (int) $validated['company_id'],
            (string) $validated['period_type'],
            (int) $validated['period_year'],
            $validated['period_type'] === FiscalPeriodService::TYPE_MONTHLY ? (int) $validated['period_month'] : null,
            (int) $request->user()->id,
            $validated['notes'] ?? null,
        );

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Periode tutup buku berhasil disimpan.',
        ]);
    }

    public function reopen(Request $request, FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        abort_if(! $request->user()?->hasRole('admin'), 403);

        $this->fiscalPeriodService->reopen($fiscalPeriod);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Periode berhasil dibuka kembali.',
        ]);
    }

    public function reopenByDate(Request $request): RedirectResponse
    {
        abort_if(! $request->user()?->hasRole('admin'), 403);

        $validated = $request->validate([
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->where('is_active', true)],
            'open_date' => ['required', 'date'],
        ]);

        $reopenedPeriods = $this->fiscalPeriodService->reopenByDate(
            (int) $validated['company_id'],
            (string) $validated['open_date'],
        );

        return redirect()->route('erp.accounting.fiscal-periods', [
            'company_id' => (int) $validated['company_id'],
            'year' => Carbon::parse((string) $validated['open_date'])->year,
        ])->with('flash', [
            'type' => 'success',
            'message' => 'Periode berhasil dibuka kembali untuk tanggal '.Carbon::parse((string) $validated['open_date'])->format('d/m/Y').': '.$reopenedPeriods
                ->map(fn (FiscalPeriod $period) => $period->period_type === FiscalPeriodService::TYPE_YEARLY
                    ? 'tahun '.$period->period_year
                    : Carbon::create($period->period_year, max($period->period_month, 1), 1)->translatedFormat('F Y'))
                ->implode(', ').'.',
        ]);
    }

    private function selectedCompanyId(Request $request): ?int
    {
        $requested = $request->query('company_id');
        if ($requested !== null && $requested !== '' && ErpCompanyResolver::isActiveCompany((int) $requested)) {
            return (int) $requested;
        }

        return ErpCompanyResolver::currentCompanyIdForSession($request);
    }
}
