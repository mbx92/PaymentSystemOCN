<?php

namespace App\Http\Controllers;

use App\Models\LandingSite;
use App\Services\CmsAccessLogger;
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
            ->with(['warehouse:id,code,name', 'page'])
            ->first();

        if ($host === 'ocnetworks.web.id') {
            if ($landing) {
                CmsAccessLogger::logLandingPublic($request, (int) $landing->id);
            }

            return Inertia::render('Public/LandingCountdown', [
                'landing' => $this->landingPayload($landing, $host, 'OCNetworks'),
                'countdownAt' => (string) config('app.ocnetworks_launch_at'),
            ]);
        }

        if ($landing) {
            CmsAccessLogger::logLandingPublic($request, (int) $landing->id);

            $page = match ($landing->layout_key) {
                'cctv' => 'Public/LandingCctv',
                'countdown' => 'Public/LandingCountdown',
                'coming_soon' => 'Public/LandingComingSoon',
                default => 'Public/LandingToko',
            };

            return Inertia::render($page, [
                'landing' => $this->landingPayload($landing, $host),
                ...( $page === 'Public/LandingCountdown' ? ['countdownAt' => (string) config('app.ocnetworks_launch_at')] : [] ),
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

    /**
     * @return array<string, mixed>
     */
    private function landingPayload(?LandingSite $landing, string $host, ?string $fallbackName = null): array
    {
        $published = (bool) ($landing?->page?->is_published ?? false);

        return [
            'name' => $landing?->name ?? $fallbackName ?? 'OCNetworks',
            'domain' => $landing?->domain ?? $host,
            'layout_key' => $landing?->layout_key ?? 'countdown',
            'warehouse' => $landing?->warehouse,
            'content' => [
                'headline' => $published ? $landing?->page?->headline : null,
                'subheadline' => $published ? $landing?->page?->subheadline : null,
                'body' => $published ? $landing?->page?->body : null,
                'primary_cta_text' => $published ? $landing?->page?->primary_cta_text : null,
                'primary_cta_url' => $published ? $landing?->page?->primary_cta_url : null,
                'secondary_cta_text' => $published ? $landing?->page?->secondary_cta_text : null,
                'secondary_cta_url' => $published ? $landing?->page?->secondary_cta_url : null,
                'contact_text' => $published ? $landing?->page?->contact_text : null,
                'seo_title' => $published ? $landing?->page?->seo_title : null,
                'seo_description' => $published ? $landing?->page?->seo_description : null,
            ],
        ];
    }
}
