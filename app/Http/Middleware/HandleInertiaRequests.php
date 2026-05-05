<?php

namespace App\Http\Middleware;

use App\Models\MasterProduct;
use Illuminate\Http\Request;
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

        return [
            ...parent::share($request),
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
        ];
    }
}
