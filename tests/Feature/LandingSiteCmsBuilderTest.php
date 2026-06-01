<?php

namespace Tests\Feature;

use App\Models\LandingSite;
use App\Models\LandingSitePage;
use App\Models\LandingSitePageVersion;
use App\Models\LandingSiteTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LandingSiteCmsBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate('admin');
    }

    public function test_cms_editor_bootstraps_draft_version_from_legacy_page_content(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $landingSite = LandingSite::query()->create([
            'name' => 'Sense Of Jewels',
            'domain' => 'senseofjewels.com',
            'layout_key' => 'coming_soon',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        LandingSitePage::query()->create([
            'landing_site_id' => $landingSite->id,
            'headline' => 'Launching Soon',
            'body' => 'Legacy copy body',
            'seo_title' => 'Legacy SEO',
            'is_published' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('erp.admin.landing-sites.cms', $landingSite))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('ERP/Admin/LandingSiteCms')
                ->where('draftVersion.document.seo.title', 'Legacy SEO')
                ->where('draftVersion.document.sections.0.props.headline', 'Launching Soon')
                ->has('availableTemplates')
                ->has('availableThemes'));

        $this->assertDatabaseHas('landing_site_page_versions', [
            'landing_site_id' => $landingSite->id,
            'status' => LandingSitePageVersion::STATUS_DRAFT,
        ]);
    }

    public function test_admin_can_save_draft_publish_and_save_template_from_builder(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $landingSite = LandingSite::query()->create([
            'name' => 'Sense Of Jewels',
            'domain' => 'senseofjewels.com',
            'layout_key' => 'countdown',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        $payload = [
            'template_key' => 'countdown-launchpad',
            'theme_key' => 'midnight-grid',
            'settings' => [
                'full_width' => true,
            ],
            'theme_overrides' => [
                'primary' => '#2563eb',
            ],
            'seo' => [
                'title' => 'Builder SEO',
                'description' => 'Builder description',
            ],
            'sections' => [
                [
                    'id' => 'hero-main',
                    'type' => 'hero',
                    'layout' => ['width' => 'full', 'variant' => 'split'],
                    'visibility' => ['enabled' => true],
                    'props' => [
                        'eyebrow' => 'OCNetworks',
                        'headline' => 'Builder Headline',
                        'subheadline' => 'Builder subheadline',
                        'body' => 'Builder body',
                        'primary_cta_text' => 'Chat Admin',
                        'primary_cta_url' => 'https://wa.me/628123',
                        'secondary_cta_text' => 'See Catalog',
                        'secondary_cta_url' => 'https://example.com/catalog',
                        'contact_text' => 'WhatsApp 08123',
                    ],
                    'assets' => [],
                ],
                [
                    'id' => 'countdown-launch',
                    'type' => 'countdown',
                    'layout' => ['width' => 'half', 'variant' => 'card'],
                    'visibility' => ['enabled' => true],
                    'props' => [
                        'title' => 'Menuju launch',
                        'subtitle' => 'Tinggal hitung mundur',
                        'target_at' => '2026-07-10T12:30',
                    ],
                    'assets' => [],
                ],
            ],
        ];

        $this->actingAs($admin)
            ->post(route('erp.admin.landing-sites.cms.update', $landingSite), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('landing_site_page_versions', [
            'landing_site_id' => $landingSite->id,
            'status' => LandingSitePageVersion::STATUS_DRAFT,
        ]);

        $draft = LandingSitePageVersion::query()
            ->where('landing_site_id', $landingSite->id)
            ->where('status', LandingSitePageVersion::STATUS_DRAFT)
            ->firstOrFail();

        $this->assertTrue((bool) data_get($draft->document, 'settings.full_width'));

        $this->actingAs($admin)
            ->post(route('erp.admin.landing-sites.cms.publish', $landingSite))
            ->assertRedirect();

        $this->assertDatabaseHas('landing_site_page_versions', [
            'landing_site_id' => $landingSite->id,
            'status' => LandingSitePageVersion::STATUS_PUBLISHED,
        ]);

        $this->actingAs($admin)
            ->post(route('erp.admin.landing-sites.cms.templates.store', $landingSite), [
                'name' => 'Sense Internal',
                'scope' => 'private',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('landing_site_templates', [
            'name' => 'Sense Internal',
            'scope' => 'private',
        ]);

        $this->assertSame('Builder Headline', $landingSite->fresh()->page?->headline);
    }

    public function test_public_page_prefers_published_builder_version(): void
    {
        $landingSite = LandingSite::query()->create([
            'name' => 'Sense Of Jewels',
            'domain' => 'senseofjewels.com',
            'layout_key' => 'coming_soon',
            'warehouse_id' => null,
            'is_active' => true,
        ]);

        $template = LandingSiteTemplate::query()->create([
            'key' => 'manual-coming-soon',
            'name' => 'Manual Coming Soon',
            'family_layout_key' => 'coming_soon',
            'scope' => 'private',
            'is_system' => false,
            'is_active' => true,
            'schema' => ['starter' => 'manual'],
        ]);

        LandingSitePageVersion::query()->create([
            'landing_site_id' => $landingSite->id,
            'template_id' => $template->id,
            'theme_id' => null,
            'status' => LandingSitePageVersion::STATUS_PUBLISHED,
            'version_no' => 3,
            'theme_overrides' => [],
            'document' => [
                'template_key' => 'manual-coming-soon',
                'theme_key' => 'ocn-clean',
                'seo' => [
                    'title' => 'Published Builder SEO',
                    'description' => 'Published builder description',
                ],
                'sections' => [
                    [
                        'id' => 'hero-main',
                        'type' => 'hero',
                        'layout' => ['width' => 'full', 'variant' => 'split'],
                        'visibility' => ['enabled' => true],
                        'props' => [
                            'eyebrow' => 'CMS Builder',
                            'headline' => 'Published Builder Headline',
                            'subheadline' => 'Published Builder Subheadline',
                            'body' => 'Published Builder Body',
                            'primary_cta_text' => 'Open WhatsApp',
                            'primary_cta_url' => 'https://wa.me/628123',
                        ],
                        'assets' => [],
                    ],
                ],
            ],
        ]);

        $this->get('http://senseofjewels.com/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Public/LandingBuilder')
                ->where('document.seo.title', 'Published Builder SEO')
                ->where('document.sections.0.props.headline', 'Published Builder Headline'));
    }
}
