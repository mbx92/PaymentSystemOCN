<?php

namespace App\Services;

use App\Models\CmsMedia;
use App\Models\LandingSite;
use App\Models\LandingSitePageVersion;
use App\Models\LandingSiteTemplate;
use App\Models\LandingSiteTheme;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class LandingSiteBuilderService
{
    /**
     * @var list<string>
     */
    private const ALLOWED_THEME_TOKENS = [
        'primary',
        'secondary',
        'accent',
        'surface',
        'surface_alt',
        'text',
        'muted',
        'success',
        'warning',
        'danger',
        'font_heading',
        'font_body',
        'radius',
        'shadow',
        'button_style',
    ];

    public function ensurePresets(): void
    {
        foreach ($this->systemTemplatePresets() as $preset) {
            LandingSiteTemplate::query()->firstOrCreate(
                ['key' => $preset['key']],
                $preset
            );
        }

        foreach ($this->systemThemePresets() as $preset) {
            LandingSiteTheme::query()->firstOrCreate(
                ['key' => $preset['key']],
                $preset
            );
        }
    }

    public function bootstrapForLandingSite(LandingSite $landingSite, ?string $templateKey = null, ?string $themeKey = null, ?User $actor = null): LandingSitePageVersion
    {
        $this->ensurePresets();

        $draft = $landingSite->pageVersions()
            ->where('status', LandingSitePageVersion::STATUS_DRAFT)
            ->first();

        if ($draft) {
            return $draft;
        }

        $template = $this->resolveTemplateForKey($templateKey, $landingSite->layout_key);
        $theme = $this->resolveThemeForKey($themeKey);
        $versionNo = $this->nextVersionNo($landingSite);

        return LandingSitePageVersion::query()->create([
            'landing_site_id' => $landingSite->id,
            'template_id' => $template?->id,
            'theme_id' => $theme?->id,
            'status' => LandingSitePageVersion::STATUS_DRAFT,
            'version_no' => $versionNo,
            'created_by' => $actor?->id,
            'theme_overrides' => [],
            'document' => $this->legacyDocumentFromLandingSite($landingSite, $template?->key, $theme?->key),
            'meta' => [
                'origin' => 'legacy_bootstrap',
            ],
        ]);
    }

    public function draftForLandingSite(LandingSite $landingSite, ?User $actor = null): LandingSitePageVersion
    {
        return $this->bootstrapForLandingSite($landingSite, null, null, $actor);
    }

    public function publishedForLandingSite(LandingSite $landingSite): ?LandingSitePageVersion
    {
        $this->ensurePresets();

        return $landingSite->pageVersions()
            ->where('status', LandingSitePageVersion::STATUS_PUBLISHED)
            ->first();
    }

    public function saveDraft(LandingSite $landingSite, array $payload, ?User $actor = null): LandingSitePageVersion
    {
        $draft = $this->draftForLandingSite($landingSite, $actor);
        $template = $this->resolveTemplateForKey((string) ($payload['template_key'] ?? ''), $landingSite->layout_key);
        $theme = $this->resolveThemeForKey((string) ($payload['theme_key'] ?? ''));
        $document = $this->normalizeDocument($landingSite, $payload, $template?->key, $theme?->key);

        $draft->fill([
            'template_id' => $template?->id,
            'theme_id' => $theme?->id,
            'theme_overrides' => $this->filterThemeOverrides((array) ($payload['theme_overrides'] ?? [])),
            'document' => $document,
            'meta' => array_merge((array) $draft->meta, [
                'last_saved_at' => now()->toIso8601String(),
            ]),
            'created_by' => $actor?->id ?? $draft->created_by,
        ])->save();

        return $draft->fresh(['template', 'theme']);
    }

    public function publishDraft(LandingSite $landingSite, ?User $actor = null): LandingSitePageVersion
    {
        $draft = $this->draftForLandingSite($landingSite, $actor)->loadMissing(['template', 'theme']);
        $published = $this->publishedForLandingSite($landingSite);

        if (! $published) {
            $published = new LandingSitePageVersion([
                'landing_site_id' => $landingSite->id,
                'status' => LandingSitePageVersion::STATUS_PUBLISHED,
            ]);
        }

        $published->fill([
            'template_id' => $draft->template_id,
            'theme_id' => $draft->theme_id,
            'version_no' => max($draft->version_no, $published->version_no ?? 0),
            'created_by' => $draft->created_by,
            'published_by' => $actor?->id,
            'published_at' => now(),
            'theme_overrides' => $draft->theme_overrides,
            'document' => $draft->document,
            'meta' => array_merge((array) $draft->meta, [
                'published_from_draft_at' => now()->toIso8601String(),
            ]),
        ]);
        $published->save();

        return $published->fresh(['template', 'theme']);
    }

    public function updateDraftSelection(LandingSite $landingSite, ?string $templateKey, ?string $themeKey, ?User $actor = null): LandingSitePageVersion
    {
        $draft = $this->draftForLandingSite($landingSite, $actor)->loadMissing(['template', 'theme']);
        $template = $templateKey ? $this->resolveTemplateForKey($templateKey, $landingSite->layout_key) : $draft->template;
        $theme = $themeKey ? $this->resolveThemeForKey($themeKey) : $draft->theme;

        $document = (array) $draft->document;
        $document['template_key'] = $template?->key ?? Arr::get($document, 'template_key', $landingSite->layout_key);
        $document['theme_key'] = $theme?->key ?? Arr::get($document, 'theme_key', 'ocn-clean');

        $draft->fill([
            'template_id' => $template?->id,
            'theme_id' => $theme?->id,
            'document' => $document,
            'created_by' => $actor?->id ?? $draft->created_by,
        ])->save();

        return $draft->fresh(['template', 'theme']);
    }

    public function saveDraftAsTemplate(LandingSite $landingSite, string $name, string $scope, ?User $actor = null): LandingSiteTemplate
    {
        $draft = $this->draftForLandingSite($landingSite, $actor)->loadMissing('template');
        $baseKey = Str::slug($name);
        $key = 'custom-'.$landingSite->id.'-'.$baseKey;
        $suffix = 1;

        while (LandingSiteTemplate::query()->where('key', $key)->exists()) {
            $suffix++;
            $key = 'custom-'.$landingSite->id.'-'.$baseKey.'-'.$suffix;
        }

        return LandingSiteTemplate::query()->create([
            'key' => $key,
            'name' => $name,
            'family_layout_key' => $landingSite->layout_key,
            'scope' => $scope,
            'is_system' => false,
            'is_active' => true,
            'created_by' => $actor?->id,
            'description' => 'Template disimpan dari draft landing site '.$landingSite->domain,
            'schema' => $draft->document,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function editorPayload(LandingSite $landingSite): array
    {
        $this->ensurePresets();
        $landingSite->loadMissing(['page', 'warehouse', 'pageVersions.template', 'pageVersions.theme']);

        $draft = $this->draftForLandingSite($landingSite)->loadMissing(['template', 'theme']);
        $published = $this->publishedForLandingSite($landingSite)?->loadMissing(['template', 'theme']);

        return [
            'availableTemplates' => $this->templateOptions($landingSite->layout_key),
            'availableThemes' => $this->themeOptions(),
            'draftVersion' => $this->versionPayload($draft),
            'publishedVersion' => $published ? $this->versionPayload($published) : null,
            'mediaLibrarySummary' => CmsMedia::query()
                ->latest()
                ->limit(120)
                ->get()
                ->map(fn (CmsMedia $m) => [
                    'id' => $m->id,
                    'name' => $m->original_name,
                    'mime' => $m->mime,
                    'url' => $m->adminLibraryUrl(),
                    'public_url' => $m->publicUrl(),
                    'alt_text' => $m->alt_text,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPublicPayload(LandingSite $landingSite, LandingSitePageVersion $version): array
    {
        $version->loadMissing(['template', 'theme']);
        $document = (array) $version->document;
        $theme = $this->resolvedThemeTokens($version);

        return [
            'document' => $document,
            'theme' => $theme,
            'version' => [
                'id' => $version->id,
                'status' => $version->status,
                'version_no' => $version->version_no,
                'published_at' => $version->published_at?->toIso8601String(),
            ],
            'landing' => [
                'name' => $landingSite->name,
                'domain' => $landingSite->domain,
                'layout_key' => $landingSite->layout_key,
                'warehouse' => $landingSite->warehouse ? [
                    'id' => $landingSite->warehouse->id,
                    'code' => $landingSite->warehouse->code,
                    'name' => $landingSite->warehouse->name,
                ] : null,
                'content' => [
                    'seo_title' => Arr::get($document, 'seo.title'),
                    'seo_description' => Arr::get($document, 'seo.description'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function versionPayload(LandingSitePageVersion $version): array
    {
        $version->loadMissing(['template', 'theme']);

        return [
            'id' => $version->id,
            'status' => $version->status,
            'version_no' => $version->version_no,
            'template_key' => $version->template?->key ?? Arr::get($version->document, 'template_key'),
            'theme_key' => $version->theme?->key ?? Arr::get($version->document, 'theme_key'),
            'theme_overrides' => (array) ($version->theme_overrides ?? []),
            'document' => $version->document ?? [],
            'published_at' => $version->published_at?->toIso8601String(),
            'theme' => $this->resolvedThemeTokens($version),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function templateOptions(string $familyLayoutKey): array
    {
        return LandingSiteTemplate::query()
            ->where('is_active', true)
            ->where(function ($query) use ($familyLayoutKey) {
                $query->where('family_layout_key', $familyLayoutKey)
                    ->orWhere('scope', 'shared_internal')
                    ->orWhere('scope', 'private');
            })
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (LandingSiteTemplate $template) => [
                'key' => $template->key,
                'name' => $template->name,
                'scope' => $template->scope,
                'family_layout_key' => $template->family_layout_key,
                'description' => $template->description,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function themeOptions(): array
    {
        return LandingSiteTheme::query()
            ->where('is_active', true)
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (LandingSiteTheme $theme) => [
                'key' => $theme->key,
                'name' => $theme->name,
                'scope' => $theme->scope,
                'description' => $theme->description,
                'tokens' => $theme->tokens ?? [],
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allTemplateOptions(): array
    {
        return LandingSiteTemplate::query()
            ->where('is_active', true)
            ->orderByDesc('is_system')
            ->orderBy('family_layout_key')
            ->orderBy('name')
            ->get()
            ->map(fn (LandingSiteTemplate $template) => [
                'key' => $template->key,
                'name' => $template->name,
                'scope' => $template->scope,
                'family_layout_key' => $template->family_layout_key,
                'description' => $template->description,
            ])
            ->all();
    }

    private function nextVersionNo(LandingSite $landingSite): int
    {
        return ((int) $landingSite->pageVersions()->max('version_no')) + 1;
    }

    private function resolveTemplateForKey(?string $key, string $familyLayoutKey): ?LandingSiteTemplate
    {
        $templateKey = $key ?: $this->defaultTemplateKeyForLayout($familyLayoutKey);

        return LandingSiteTemplate::query()
            ->where('key', $templateKey)
            ->first();
    }

    private function resolveThemeForKey(?string $key): ?LandingSiteTheme
    {
        $themeKey = $key ?: 'ocn-clean';

        return LandingSiteTheme::query()
            ->where('key', $themeKey)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyDocumentFromLandingSite(LandingSite $landingSite, ?string $templateKey, ?string $themeKey): array
    {
        $page = $landingSite->page;
        $headline = $page?->headline ?: $this->defaultHeadlineForLayout($landingSite->layout_key);
        $subheadline = $page?->subheadline ?: null;
        $body = $page?->body ?: $this->defaultBodyForLayout($landingSite);
        $contactText = $page?->contact_text ?: null;
        $countdownAt = $page?->countdown_at?->toIso8601String();

        $sections = [
            [
                'id' => 'hero-main',
                'type' => 'hero',
                'layout' => ['width' => 'full', 'variant' => 'split'],
                'visibility' => ['enabled' => true],
                'props' => [
                    'eyebrow' => 'OCNetworks',
                    'headline' => $headline,
                    'subheadline' => $subheadline,
                    'body' => $body,
                    'primary_cta_text' => $page?->primary_cta_text,
                    'primary_cta_url' => $page?->primary_cta_url,
                    'secondary_cta_text' => $page?->secondary_cta_text,
                    'secondary_cta_url' => $page?->secondary_cta_url,
                    'contact_text' => $contactText,
                ],
                'assets' => [
                    'hero_media_url' => null,
                ],
            ],
            [
                'id' => 'info-grid',
                'type' => 'warehouse_highlight',
                'layout' => ['width' => 'full', 'variant' => 'cards'],
                'visibility' => ['enabled' => true],
                'props' => [
                    'title' => 'Informasi domain',
                    'body' => 'Landing ini terhubung ke domain publik dan dapat diarahkan ke business unit yang sesuai.',
                    'show_domain' => true,
                    'show_warehouse' => true,
                ],
                'assets' => [],
            ],
            [
                'id' => 'contact-card',
                'type' => 'contact_card',
                'layout' => ['width' => 'half', 'variant' => 'card'],
                'visibility' => ['enabled' => $contactText !== null || $page?->primary_cta_text !== null],
                'props' => [
                    'title' => 'Hubungi kami',
                    'body' => $contactText ?: 'Gunakan CTA di bawah untuk menghubungi tim kami.',
                    'primary_cta_text' => $page?->primary_cta_text,
                    'primary_cta_url' => $page?->primary_cta_url,
                    'secondary_cta_text' => $page?->secondary_cta_text,
                    'secondary_cta_url' => $page?->secondary_cta_url,
                ],
                'assets' => [],
            ],
        ];

        if ($landingSite->layout_key === 'countdown') {
            $sections[] = [
                'id' => 'countdown-launch',
                'type' => 'countdown',
                'layout' => ['width' => 'half', 'variant' => 'card'],
                'visibility' => ['enabled' => true],
                'props' => [
                    'title' => 'Menuju peluncuran',
                    'subtitle' => $subheadline ?: 'Countdown aktif untuk domain ini.',
                    'target_at' => $countdownAt,
                ],
                'assets' => [],
            ];
        }

        return [
            'template_key' => $templateKey ?: $this->defaultTemplateKeyForLayout($landingSite->layout_key),
            'theme_key' => $themeKey ?: 'ocn-clean',
            'settings' => [
                'full_width' => false,
            ],
            'seo' => [
                'title' => $page?->seo_title ?: $landingSite->name,
                'description' => $page?->seo_description ?: $body,
            ],
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeDocument(LandingSite $landingSite, array $payload, ?string $templateKey, ?string $themeKey): array
    {
        $sections = collect((array) ($payload['sections'] ?? []))
            ->map(function ($section, int $index) {
                $section = is_array($section) ? $section : [];

                return [
                    'id' => (string) ($section['id'] ?? 'section-'.($index + 1)),
                    'type' => (string) ($section['type'] ?? 'text'),
                    'layout' => [
                        'width' => Arr::get($section, 'layout.width', 'full'),
                        'variant' => Arr::get($section, 'layout.variant', 'default'),
                    ],
                    'visibility' => [
                        'enabled' => (bool) Arr::get($section, 'visibility.enabled', true),
                    ],
                    'props' => is_array($section['props'] ?? null) ? $section['props'] : [],
                    'assets' => is_array($section['assets'] ?? null) ? $section['assets'] : [],
                ];
            })
            ->values()
            ->all();

        if ($sections === []) {
            return $this->legacyDocumentFromLandingSite($landingSite, $templateKey, $themeKey);
        }

        return [
            'template_key' => $templateKey ?: $this->defaultTemplateKeyForLayout($landingSite->layout_key),
            'theme_key' => $themeKey ?: 'ocn-clean',
            'settings' => [
                'full_width' => (bool) Arr::get($payload, 'settings.full_width', false),
            ],
            'seo' => [
                'title' => trim((string) Arr::get($payload, 'seo.title', '')) ?: $landingSite->name,
                'description' => trim((string) Arr::get($payload, 'seo.description', '')) ?: null,
            ],
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function filterThemeOverrides(array $overrides): array
    {
        return collect(self::ALLOWED_THEME_TOKENS)
            ->filter(fn (string $key) => array_key_exists($key, $overrides))
            ->mapWithKeys(fn (string $key) => [$key => $overrides[$key]])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedThemeTokens(LandingSitePageVersion $version): array
    {
        $theme = $version->theme;

        if (! $theme && ($themeKey = Arr::get($version->document, 'theme_key'))) {
            $theme = LandingSiteTheme::query()->where('key', $themeKey)->first();
        }

        $base = is_array($theme?->tokens) ? $theme->tokens : [];

        return array_merge($base, $this->filterThemeOverrides((array) ($version->theme_overrides ?? [])));
    }

    private function defaultTemplateKeyForLayout(string $layoutKey): string
    {
        return match ($layoutKey) {
            'cctv' => 'cctv-service',
            'coming_soon' => 'coming-soon-glow',
            'countdown' => 'countdown-launchpad',
            default => 'toko-conversion',
        };
    }

    private function defaultHeadlineForLayout(string $layoutKey): string
    {
        return match ($layoutKey) {
            'cctv' => 'CCTV & solusi jaringan',
            'coming_soon' => 'Website sedang disiapkan',
            'countdown' => 'Website resmi baru sedang menuju peluncuran.',
            default => 'Toko & produk retail',
        };
    }

    private function defaultBodyForLayout(LandingSite $landingSite): string
    {
        return match ($landingSite->layout_key) {
            'cctv' => 'Landing untuk bisnis instalasi, project, dan jaringan. Tampilkan layanan utama, kontak cepat, dan konteks warehouse yang terkait.',
            'coming_soon' => 'Halaman resmi untuk '.$landingSite->name.' sedang dipersiapkan lengkap dengan informasi layanan dan kanal kontak.',
            'countdown' => 'Kami sedang menyiapkan halaman publik baru dengan presentasi layanan, profil perusahaan, dan kanal kontak yang lebih rapi.',
            default => 'Landing untuk penjualan toko. Produk dan informasi bisnis dapat disesuaikan per domain.',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function systemTemplatePresets(): array
    {
        return [
            [
                'key' => 'toko-conversion',
                'name' => 'Toko Conversion',
                'family_layout_key' => 'toko',
                'scope' => 'system',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Template retail dengan hero, highlight bisnis, dan CTA penjualan.',
                'schema' => ['starter' => 'toko'],
            ],
            [
                'key' => 'cctv-service',
                'name' => 'CCTV Service',
                'family_layout_key' => 'cctv',
                'scope' => 'system',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Template layanan CCTV dan jaringan berorientasi lead.',
                'schema' => ['starter' => 'cctv'],
            ],
            [
                'key' => 'coming-soon-glow',
                'name' => 'Coming Soon Glow',
                'family_layout_key' => 'coming_soon',
                'scope' => 'system',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Template coming soon sederhana dengan fokus status dan CTA.',
                'schema' => ['starter' => 'coming_soon'],
            ],
            [
                'key' => 'countdown-launchpad',
                'name' => 'Countdown Launchpad',
                'family_layout_key' => 'countdown',
                'scope' => 'system',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Template countdown dengan hero dan panel waktu peluncuran.',
                'schema' => ['starter' => 'countdown'],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function systemThemePresets(): array
    {
        return [
            [
                'key' => 'ocn-clean',
                'name' => 'OCN Clean',
                'scope' => 'system',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Tema terang OCN untuk corporate landing.',
                'tokens' => [
                    'primary' => '#1d4ed8',
                    'secondary' => '#0f172a',
                    'accent' => '#0ea5e9',
                    'surface' => '#f8fafc',
                    'surface_alt' => '#e2e8f0',
                    'text' => '#0f172a',
                    'muted' => '#475569',
                    'success' => '#15803d',
                    'warning' => '#b45309',
                    'danger' => '#b91c1c',
                    'font_heading' => 'Figtree, sans-serif',
                    'font_body' => 'Figtree, sans-serif',
                    'radius' => '24px',
                    'shadow' => '0 30px 80px rgba(15, 23, 42, 0.12)',
                    'button_style' => 'rounded',
                ],
            ],
            [
                'key' => 'midnight-grid',
                'name' => 'Midnight Grid',
                'scope' => 'system',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Tema gelap dengan aksen cyan untuk countdown dan launch.',
                'tokens' => [
                    'primary' => '#22d3ee',
                    'secondary' => '#07111f',
                    'accent' => '#34d399',
                    'surface' => '#081018',
                    'surface_alt' => '#132235',
                    'text' => '#f8fafc',
                    'muted' => '#cbd5e1',
                    'success' => '#34d399',
                    'warning' => '#f59e0b',
                    'danger' => '#f87171',
                    'font_heading' => 'Figtree, sans-serif',
                    'font_body' => 'Figtree, sans-serif',
                    'radius' => '28px',
                    'shadow' => '0 30px 80px rgba(0, 0, 0, 0.4)',
                    'button_style' => 'pill',
                ],
            ],
            [
                'key' => 'sunset-coral',
                'name' => 'Sunset Coral',
                'scope' => 'system',
                'is_system' => true,
                'is_active' => true,
                'description' => 'Tema hangat untuk landing promosi dan retail.',
                'tokens' => [
                    'primary' => '#ea580c',
                    'secondary' => '#7c2d12',
                    'accent' => '#fb7185',
                    'surface' => '#fff7ed',
                    'surface_alt' => '#fed7aa',
                    'text' => '#431407',
                    'muted' => '#9a3412',
                    'success' => '#15803d',
                    'warning' => '#b45309',
                    'danger' => '#b91c1c',
                    'font_heading' => 'Figtree, sans-serif',
                    'font_body' => 'Figtree, sans-serif',
                    'radius' => '20px',
                    'shadow' => '0 24px 70px rgba(194, 65, 12, 0.18)',
                    'button_style' => 'rounded',
                ],
            ],
        ];
    }
}
