<?php

namespace App\Http\Controllers;

use App\ERP\Inventory\Models\Warehouse;
use App\Models\CmsAccessLog;
use App\Models\CmsMedia;
use App\Models\LandingSite;
use App\Models\LandingSitePage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CmsModuleController extends Controller
{
    public function dashboard(Request $request): Response
    {
        return Inertia::render('CMS/Dashboard', [
            'stats' => [
                'sites_total' => LandingSite::query()->count(),
                'sites_active' => LandingSite::query()->where('is_active', true)->count(),
                'pages_published' => LandingSitePage::query()->where('is_published', true)->count(),
                'media_total' => CmsMedia::query()->count(),
                'media_bytes' => (int) CmsMedia::query()->sum('size_bytes'),
            ],
            'visitAnalytics' => $this->buildVisitAnalytics(),
            'recentAccess' => $this->buildRecentAccess($request),
            'recentAccessFilters' => $this->filtersWithPerPage($request, ['kind', 'device_type', 'q']),
            'recentAccessOptions' => [
                'kinds' => [
                    ['value' => CmsAccessLog::KIND_LANDING_PUBLIC, 'label' => 'Landing publik'],
                    ['value' => CmsAccessLog::KIND_CMS_ADMIN, 'label' => 'Panel CMS'],
                ],
                'device_types' => $this->recentAccessDeviceTypes(),
            ],
        ]);
    }

    public function sites(Request $request): Response
    {
        return Inertia::render('ERP/Admin/LandingSites', [
            'landingSites' => LandingSite::query()
                ->with(['warehouse:id,code,name', 'page:id,landing_site_id,is_published'])
                ->orderBy('is_active', 'desc')
                ->orderBy('name')
                ->paginate($this->resolvedPerPage($request))
                ->withQueryString(),
            'warehouses' => Warehouse::query()
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'filters' => $this->filtersWithPerPage($request, []),
            'cmsModule' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVisitAnalytics(): array
    {
        $rangeDays = 14;
        $since = now()->subDays($rangeDays - 1)->startOfDay();
        $weekAgo = now()->subDays(6)->startOfDay();

        $labelDates = collect(range($rangeDays - 1, 0))
            ->map(fn (int $i) => now()->subDays($i)->toDateString())
            ->values()
            ->all();

        $landingLogs = CmsAccessLog::query()
            ->where('kind', CmsAccessLog::KIND_LANDING_PUBLIC)
            ->where('created_at', '>=', $since)
            ->get(['created_at', 'ip_address']);

        $adminLogs = CmsAccessLog::query()
            ->where('kind', CmsAccessLog::KIND_CMS_ADMIN)
            ->where('created_at', '>=', $since)
            ->get(['created_at', 'ip_address']);

        $landingSeries = $this->aggregateHitsByDay($landingLogs, $labelDates);
        $adminSeries = $this->aggregateHitsByDay($adminLogs, $labelDates);

        $chartLabels = collect($labelDates)
            ->map(fn (string $d) => Carbon::parse($d)->format('d/m'))
            ->all();

        $topCountries = CmsAccessLog::query()
            ->where('kind', CmsAccessLog::KIND_LANDING_PUBLIC)
            ->where('created_at', '>=', $since)
            ->whereNotNull('country_name')
            ->selectRaw('country_name, COUNT(*) as cnt')
            ->groupBy('country_name')
            ->orderByDesc('cnt')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->country_name, 'value' => (int) $row->cnt])
            ->all();

        $devices = CmsAccessLog::query()
            ->where('kind', CmsAccessLog::KIND_LANDING_PUBLIC)
            ->where('created_at', '>=', $since)
            ->selectRaw('device_type, COUNT(*) as cnt')
            ->groupBy('device_type')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->device_type, 'value' => (int) $row->cnt])
            ->all();

        return [
            'range_days' => $rangeDays,
            'summary' => [
                'landing_hits_7d' => (int) CmsAccessLog::query()
                    ->where('kind', CmsAccessLog::KIND_LANDING_PUBLIC)
                    ->where('created_at', '>=', $weekAgo)
                    ->count(),
                'landing_unique_ip_7d' => (int) CmsAccessLog::query()
                    ->where('kind', CmsAccessLog::KIND_LANDING_PUBLIC)
                    ->where('created_at', '>=', $weekAgo)
                    ->selectRaw('count(distinct ip_address) as c')
                    ->value('c'),
                'admin_hits_7d' => (int) CmsAccessLog::query()
                    ->where('kind', CmsAccessLog::KIND_CMS_ADMIN)
                    ->where('created_at', '>=', $weekAgo)
                    ->count(),
            ],
            'timeseries' => [
                'labels' => $chartLabels,
                'landing_hits' => $landingSeries['hits'],
                'landing_unique_ips' => $landingSeries['unique_ips'],
                'admin_hits' => $adminSeries['hits'],
            ],
            'devices' => $devices,
            'countries' => $topCountries,
        ];
    }

    private function buildRecentAccess(Request $request)
    {
        $query = CmsAccessLog::query()
            ->with([
                'landingSite:id,name,domain',
                'user:id,name,email',
            ]);

        if ($kind = $request->string('kind')->toString()) {
            $query->where('kind', $kind);
        }

        if ($deviceType = $request->string('device_type')->toString()) {
            $query->where('device_type', $deviceType);
        }

        if ($q = trim($request->string('q')->toString())) {
            $query->where(function ($inner) use ($q) {
                $inner->where('ip_address', 'like', "%{$q}%")
                    ->orWhere('path', 'like', "%{$q}%")
                    ->orWhere('browser', 'like', "%{$q}%")
                    ->orWhere('os', 'like', "%{$q}%")
                    ->orWhere('city', 'like', "%{$q}%")
                    ->orWhere('region_name', 'like', "%{$q}%")
                    ->orWhere('country_name', 'like', "%{$q}%")
                    ->orWhereHas('landingSite', fn ($site) => $site->where('name', 'like', "%{$q}%")->orWhere('domain', 'like', "%{$q}%"))
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
            });
        }

        return $query
            ->orderByDesc('id')
            ->paginate($this->resolvedPerPage($request))
            ->withQueryString()
            ->through(fn (CmsAccessLog $log) => $this->mapRecentAccessRow($log));
    }

    /**
     * @return list<string>
     */
    private function recentAccessDeviceTypes(): array
    {
        return CmsAccessLog::query()
            ->whereNotNull('device_type')
            ->distinct()
            ->orderBy('device_type')
            ->pluck('device_type')
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRecentAccessRow(CmsAccessLog $log): array
    {
        $loc = array_filter([$log->city, $log->region_name, $log->country_name]);

        return [
            'at' => $log->created_at?->toIso8601String(),
            'at_display' => $log->created_at?->format('d M Y, H:i'),
            'kind' => $log->kind,
            'kind_label' => $log->kind === CmsAccessLog::KIND_LANDING_PUBLIC ? 'Landing publik' : 'Panel CMS',
            'ip' => $log->ip_address,
            'location' => $loc === [] ? '—' : implode(', ', $loc),
            'device_type' => $log->device_type,
            'browser' => $log->browser ?? '—',
            'os' => $log->os ?? '—',
            'site' => $log->landingSite?->name,
            'user' => $log->user?->email,
            'path' => $log->path,
        ];
    }

    /**
     * @param  Collection<int, CmsAccessLog>  $rows
     * @param  list<string>  $labelDates  Y-m-d keys in order
     * @return array{hits: list<int>, unique_ips: list<int>}
     */
    private function aggregateHitsByDay(SupportCollection $rows, array $labelDates): array
    {
        $hits = array_fill_keys($labelDates, 0);
        $uniqueBuckets = array_fill_keys($labelDates, []);

        foreach ($rows as $row) {
            $d = $row->created_at?->toDateString();
            if ($d === null || ! array_key_exists($d, $hits)) {
                continue;
            }
            $hits[$d]++;
            $uniqueBuckets[$d][$row->ip_address] = true;
        }

        return [
            'hits' => array_values(array_map(fn (string $d) => $hits[$d], $labelDates)),
            'unique_ips' => array_values(array_map(fn (string $d) => count($uniqueBuckets[$d]), $labelDates)),
        ];
    }
}
