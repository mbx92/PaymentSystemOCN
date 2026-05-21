<?php

namespace App\Http\Controllers;

use App\Models\LandingSite;
use App\Services\CmsAccessLogger;
use Illuminate\Http\JsonResponse;
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
        $landing = $this->resolveLanding($host);

        if ($host === 'ocnetworks.web.id') {
            if ($landing) {
                CmsAccessLogger::logLandingPublic($request, (int) $landing->id);
            }

            return Inertia::render('Public/LandingCountdown', [
                'landing' => $this->landingPayload($landing, $host, 'OCNetworks'),
                'countdownAt' => $this->resolveCountdownAt($landing),
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
                ...($page === 'Public/LandingCountdown' ? ['countdownAt' => $this->resolveCountdownAt($landing)] : []),
            ]);
        }

        if (Auth::check()) {
            return app(DashboardController::class)->index($request);
        }

        return redirect()->route('login');
    }

    public function track(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'event_name' => ['required', 'string', 'in:cta_click,page_exit'],
            'event_meta' => ['nullable', 'array'],
            'event_meta.cta_kind' => ['nullable', 'string', 'max:50'],
            'event_meta.cta_text' => ['nullable', 'string', 'max:255'],
            'event_meta.cta_url' => ['nullable', 'string', 'max:500'],
            'event_meta.active_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'event_meta.visible_ms' => ['nullable', 'integer', 'min:0', 'max:86400000'],
            'event_meta.max_scroll_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $landing = $this->resolveLanding($this->normalizeHost($request));

        if (! $landing) {
            return response()->json(['ok' => true]);
        }

        CmsAccessLogger::logLandingEvent(
            $request,
            (int) $landing->id,
            (string) $payload['event_name'],
            (array) ($payload['event_meta'] ?? []),
        );

        return response()->json(['ok' => true]);
    }

    private function normalizeHost(Request $request): string
    {
        $host = strtolower($request->getHost());

        return preg_replace('/:\d+$/', '', $host);
    }

    private function resolveLanding(string $host): ?LandingSite
    {
        return LandingSite::query()
            ->where('domain', $host)
            ->where('is_active', true)
            ->with(['warehouse:id,code,name', 'page'])
            ->first();
    }

    private function resolveCountdownAt(?LandingSite $landing): string
    {
        return $landing?->page?->countdown_at?->toIso8601String()
            ?? (string) config('app.ocnetworks_launch_at');
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
