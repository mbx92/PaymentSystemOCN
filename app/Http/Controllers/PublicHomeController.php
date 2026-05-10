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

        if ($landing) {
            CmsAccessLogger::logLandingPublic($request, (int) $landing->id);

            $page = match ($landing->layout_key) {
                'cctv' => 'Public/LandingCctv',
                'coming_soon' => 'Public/LandingComingSoon',
                default => 'Public/LandingToko',
            };

            return Inertia::render($page, [
                'landing' => [
                    'name' => $landing->name,
                    'domain' => $landing->domain,
                    'layout_key' => $landing->layout_key,
                    'warehouse' => $landing->warehouse,
                    'content' => [
                        'headline' => $landing->page?->is_published ? $landing->page?->headline : null,
                        'subheadline' => $landing->page?->is_published ? $landing->page?->subheadline : null,
                        'body' => $landing->page?->is_published ? $landing->page?->body : null,
                        'primary_cta_text' => $landing->page?->is_published ? $landing->page?->primary_cta_text : null,
                        'primary_cta_url' => $landing->page?->is_published ? $landing->page?->primary_cta_url : null,
                        'secondary_cta_text' => $landing->page?->is_published ? $landing->page?->secondary_cta_text : null,
                        'secondary_cta_url' => $landing->page?->is_published ? $landing->page?->secondary_cta_url : null,
                        'contact_text' => $landing->page?->is_published ? $landing->page?->contact_text : null,
                        'seo_title' => $landing->page?->is_published ? $landing->page?->seo_title : null,
                        'seo_description' => $landing->page?->is_published ? $landing->page?->seo_description : null,
                    ],
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
