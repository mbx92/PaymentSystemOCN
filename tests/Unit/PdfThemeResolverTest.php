<?php

namespace Tests\Unit;

use App\Models\ErpSetting;
use App\Services\PdfThemeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PdfThemeResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_uses_placeholders_when_erp_settings_empty(): void
    {
        $brand = app(PdfThemeResolver::class)->brand();

        $this->assertFalse($brand['has_logo']);
        $this->assertTrue($brand['use_title_placeholder']);
        $this->assertTrue($brand['use_tagline_placeholder']);
        $this->assertSame('Nama Perusahaan', $brand['title_placeholder']);
    }

    public function test_brand_uses_erp_settings_when_configured(): void
    {
        ErpSetting::query()->create([
            'app_name' => 'PT Contoh',
            'app_tagline' => 'Solusi CCTV',
        ]);

        $brand = app(PdfThemeResolver::class)->brand();

        $this->assertFalse($brand['use_title_placeholder']);
        $this->assertSame('PT Contoh', $brand['title']);
        $this->assertSame('Solusi CCTV', $brand['tagline']);
    }

    public function test_theme_matches_ocn_primary_color(): void
    {
        $theme = app(PdfThemeResolver::class)->theme();

        $this->assertSame('#1d4ed8', $theme['primary']);
        $this->assertSame('#ffffff', $theme['primary_content']);
    }
}
