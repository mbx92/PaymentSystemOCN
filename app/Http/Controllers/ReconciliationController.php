<?php

namespace App\Http\Controllers;

use App\ERP\Accounting\Models\Account;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\CashIn;
use App\Models\CashOut;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        $period = $request->string('period')->toString() ?: 'daily';
        $periodSql = $period === 'weekly' ? "to_char(date_trunc('week', date), 'IYYY-\"W\"IW')" : "to_char(date, 'YYYY-MM-DD')";
        $companyId = ErpCompanyResolver::resolveForReporting($request);

        $cashInRows = CashIn::query()
            ->selectRaw("{$periodSql} as bucket, cash_account_id, sum(amount) as total_in")
            ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn ($jq) => $jq->where('company_id', $companyId)))
            ->groupByRaw('bucket, cash_account_id')
            ->get();

        $cashOutRows = CashOut::query()
            ->selectRaw("{$periodSql} as bucket, cash_account_id, sum(amount) as total_out")
            ->when($companyId, fn ($q) => $q->whereHas('journalEntry', fn ($jq) => $jq->where('company_id', $companyId)))
            ->groupByRaw('bucket, cash_account_id')
            ->get();

        $accounts = Account::query()->pluck('name', 'id');
        $map = [];
        foreach ($cashInRows as $row) {
            $key = $row->bucket.'|'.$row->cash_account_id;
            $map[$key] = $map[$key] ?? ['bucket' => $row->bucket, 'cash_account_id' => $row->cash_account_id, 'cash_in' => 0.0, 'cash_out' => 0.0];
            $map[$key]['cash_in'] = (float) $row->total_in;
        }
        foreach ($cashOutRows as $row) {
            $key = $row->bucket.'|'.$row->cash_account_id;
            $map[$key] = $map[$key] ?? ['bucket' => $row->bucket, 'cash_account_id' => $row->cash_account_id, 'cash_in' => 0.0, 'cash_out' => 0.0];
            $map[$key]['cash_out'] = (float) $row->total_out;
        }

        $rows = collect($map)
            ->map(fn ($r) => [
                'bucket' => $r['bucket'],
                'cash_account_id' => $r['cash_account_id'],
                'cash_account_name' => $accounts[$r['cash_account_id']] ?? 'Kas/Bank Tidak Dikenal',
                'cash_in' => $r['cash_in'],
                'cash_out' => $r['cash_out'],
                'net' => $r['cash_in'] - $r['cash_out'],
            ])
            ->filter(function (array $row) use ($request): bool {
                $term = trim((string) $request->string('q'));
                if ($term === '') {
                    return true;
                }

                $haystack = strtolower($row['bucket'].' '.$row['cash_account_name']);

                return str_contains($haystack, strtolower($term));
            })
            ->sortByDesc(fn ($r) => $r['bucket'])
            ->values();

        $perPage = $this->resolvedPerPage($request);
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $paginatedRows = new LengthAwarePaginator(
            $rows->forPage($currentPage, $perPage)->values(),
            $rows->count(),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return Inertia::render('ERP/Accounting/Reconciliation', [
            'period' => $period,
            'rows' => $paginatedRows,
            'filters' => $this->filtersWithPerPage($request, ['company_id', 'q']),
        ]);
    }
}
