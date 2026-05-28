<?php

namespace App\Http\Middleware;

use App\ERP\Core\Models\Company;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\ErpSetting;
use App\Models\User;
use App\Support\AppNotificationCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $erpSetting = ErpSetting::query()->first();
        $erpSettingArray = $this->erpSettingArray($erpSetting);

        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name,
                ] : null,
                'permissions' => $user
                    ? $user->getAllPermissions()->pluck('name')->filter(fn (string $name): bool => str_starts_with($name, 'menu.'))->values()->all()
                    : [],
            ],
            'flash' => fn () => $request->session()->get('flash'),
            'devLoginSeed' => fn () => $request->session()->get('devLoginSeed'),
            'notificationCenter' => fn () => app(AppNotificationCenter::class)->buildFor($user),
            'inventoryAlerts' => fn () => $request->path() !== '' && str_starts_with($request->path(), 'erp/inventory')
                ? $this->resolveInventoryAlerts()
                : ['lowStockCount' => 0, 'lowStockItems' => []],
            'erpSetting' => fn () => $erpSettingArray,
            'erpCompanyContext' => fn () => $this->erpCompanyContextProps($request),
            'uiPreferences' => fn () => $user ? $user->resolvedUiPreferences() : User::defaultUiPreferences(),
            'maintenance' => fn () => [
                'global' => (bool) ($erpSetting?->maintenance_global_enabled ?? false),
                'modules' => $erpSetting !== null
                    ? $erpSetting->mergedMaintenanceModules()
                    : ErpSetting::defaultMaintenanceModules(),
            ],
        ];
    }

    private function erpCompanyContextProps(Request $request): ?array
    {
        if (! $request->user()) {
            return null;
        }

        $companies = Cache::remember('active_companies', 3600, function (): \Illuminate\Database\Eloquent\Collection {
            return Company::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'legal_name']);
        });

        if ($companies->isEmpty()) {
            return null;
        }

        return [
            'companies' => $companies,
            'current_company_id' => ErpCompanyResolver::currentCompanyIdForSession($request),
        ];
    }

    private function resolveInventoryAlerts(): array
    {
        $lowStockItems = \App\Models\MasterProduct::query()
            ->where('product_type', '!=', \App\Models\MasterProduct::PRODUCT_TYPE_SERVICE)
            ->where('low_stock_alert_enabled', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('stock')
            ->limit(5)
            ->get(['id', 'sku', 'name', 'stock', 'min_stock', 'low_stock_alert_enabled']);

        return [
            'lowStockCount' => $lowStockItems->count(),
            'lowStockItems' => $lowStockItems,
        ];
    }

    private function erpSettingArray(?ErpSetting $erpSetting): array
    {
        return [
            'app_name' => $erpSetting?->app_name ?? 'OCN ERP Suite',
            'app_tagline' => $erpSetting?->app_tagline ?? 'Integrated Business Platform',
            'app_logo_url' => $erpSetting?->app_logo_path ? Storage::url($erpSetting->app_logo_path) : null,
            'module_menu_layout' => $erpSetting?->resolvedModuleMenuLayout() ?? ErpSetting::MODULE_MENU_LAYOUT_GRID,
            'screen_mode' => $erpSetting?->resolvedScreenMode() ?? ErpSetting::SCREEN_MODE_AUTO,
            'screen_density' => $erpSetting?->resolvedScreenDensity() ?? ErpSetting::SCREEN_DENSITY_COMFORTABLE,
        ];
    }
}
