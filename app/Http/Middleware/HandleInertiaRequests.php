<?php

namespace App\Http\Middleware;

use App\ERP\Core\Models\Company;
use App\ERP\Core\Services\ErpCompanyResolver;
use App\Models\ErpSetting;
use App\Models\MasterProduct;
use App\Models\User;
use App\Support\AppNotificationCenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $erpSetting = ErpSetting::query()->first();
        $notificationCenter = app(AppNotificationCenter::class)->buildFor($user);
        $lowStockGroup = collect($notificationCenter['groups'] ?? [])->firstWhere('key', 'low_stock');

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
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
            ],
            'flash' => fn () => $request->session()->get('flash'),
            'devLoginSeed' => fn () => $request->session()->get('devLoginSeed'),
            'inventoryAlerts' => fn () => [
                'lowStockCount' => (int) ($lowStockGroup['count'] ?? 0),
                'lowStockItems' => MasterProduct::query()
                    ->where('product_type', '!=', MasterProduct::PRODUCT_TYPE_SERVICE)
                    ->where('low_stock_alert_enabled', true)
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->orderBy('stock')
                    ->limit(5)
                    ->get(['id', 'sku', 'name', 'stock', 'min_stock', 'low_stock_alert_enabled']),
            ],
            'notificationCenter' => fn () => $notificationCenter,
            'erpSetting' => fn () => [
                'app_name' => $erpSetting?->app_name ?? 'OCN ERP Suite',
                'app_tagline' => $erpSetting?->app_tagline ?? 'Integrated Business Platform',
                'app_logo_url' => $erpSetting?->app_logo_path ? Storage::url($erpSetting->app_logo_path) : null,
                'module_menu_layout' => $erpSetting?->resolvedModuleMenuLayout() ?? ErpSetting::MODULE_MENU_LAYOUT_GRID,
                'screen_mode' => $erpSetting?->resolvedScreenMode() ?? ErpSetting::SCREEN_MODE_AUTO,
                'screen_density' => $erpSetting?->resolvedScreenDensity() ?? ErpSetting::SCREEN_DENSITY_COMFORTABLE,
            ],
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

    /**
     * @return array<string, mixed>|null
     */
    private function erpCompanyContextProps(Request $request): ?array
    {
        if (! $request->user()) {
            return null;
        }

        $companies = Company::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'legal_name']);

        if ($companies->isEmpty()) {
            return null;
        }

        return [
            'companies' => $companies,
            'current_company_id' => ErpCompanyResolver::currentCompanyIdForSession($request),
        ];
    }
}
