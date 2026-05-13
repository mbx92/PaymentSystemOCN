<?php

namespace App\Http\Middleware;

use App\Models\ErpSetting;
use App\Models\MasterProduct;
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
            ],
            'flash' => fn () => $request->session()->get('flash'),
            'devLoginSeed' => fn () => $request->session()->get('devLoginSeed'),
            'inventoryAlerts' => fn () => [
                'lowStockCount' => MasterProduct::query()->whereColumn('stock', '<=', 'min_stock')->count(),
                'lowStockItems' => MasterProduct::query()
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->orderBy('stock')
                    ->limit(5)
                    ->get(['id', 'sku', 'name', 'stock', 'min_stock']),
            ],
            'erpSetting' => fn () => [
                'app_name' => $erpSetting?->app_name ?? 'OCN ERP Suite',
                'app_tagline' => $erpSetting?->app_tagline ?? 'Integrated Business Platform',
                'app_logo_url' => $erpSetting?->app_logo_path ? Storage::url($erpSetting->app_logo_path) : null,
            ],
            'maintenance' => fn () => [
                'global' => (bool) ($erpSetting?->maintenance_global_enabled ?? false),
                'modules' => $erpSetting !== null
                    ? $erpSetting->mergedMaintenanceModules()
                    : ErpSetting::defaultMaintenanceModules(),
            ],
        ];
    }
}
