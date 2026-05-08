<?php

namespace App\Http\Controllers;

use App\Models\LandingSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PublicHomeController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $host = $this->normalizeHost($request);

        $landing = LandingSite::query()
            ->where('domain', $host)
            ->where('is_active', true)
            ->with(['warehouse:id,code,name'])
            ->first();

        if ($landing) {
            $page = match ($landing->layout_key) {
                'cctv' => 'Public/LandingCctv',
                default => 'Public/LandingToko',
            };

            return Inertia::render($page, [
                'landing' => [
                    'name' => $landing->name,
                    'domain' => $landing->domain,
                    'layout_key' => $landing->layout_key,
                    'warehouse' => $landing->warehouse,
                ],
            ]);
        }

        if (Auth::check()) {
            return app(DashboardController::class)->index($request);
        }

        return redirect()->route('login');
    }

    private function normalizeHost(Request $request): string
    {
        $host = strtolower($request->getHost());

        return preg_replace('/:\d+$/', '', $host);
    }
}
