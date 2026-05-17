<?php

namespace App\Services;

use App\Models\ErpSetting;
use Illuminate\Support\Facades\Storage;

class PdfThemeResolver
{
    /**
     * @return array<string, string>
     */
    public function theme(): array
    {
        return config('pdf.theme', []);
    }

    /**
     * @return array{
     *     has_logo: bool,
     *     logo_data_uri: string|null,
     *     logo_placeholder: string,
     *     title: string|null,
     *     title_placeholder: string,
     *     tagline: string|null,
     *     tagline_placeholder: string,
     *     use_title_placeholder: bool,
     *     use_tagline_placeholder: bool
     * }
     */
    public function brand(): array
    {
        $setting = ErpSetting::query()->first();
        $placeholders = config('pdf.placeholders', []);

        $logoDataUri = null;
        if ($setting?->app_logo_path && Storage::disk('public')->exists($setting->app_logo_path)) {
            $path = Storage::disk('public')->path($setting->app_logo_path);
            $mime = function_exists('mime_content_type') ? mime_content_type($path) : 'image/png';
            $logoDataUri = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($path));
        }

        $title = trim((string) ($setting?->app_name ?? ''));
        $tagline = trim((string) ($setting?->app_tagline ?? ''));

        return [
            'has_logo' => $logoDataUri !== null,
            'logo_data_uri' => $logoDataUri,
            'logo_placeholder' => (string) ($placeholders['logo'] ?? 'Logo'),
            'title' => $title !== '' ? $title : null,
            'title_placeholder' => (string) ($placeholders['title'] ?? 'Nama Perusahaan'),
            'tagline' => $tagline !== '' ? $tagline : null,
            'tagline_placeholder' => (string) ($placeholders['tagline'] ?? 'Tagline Perusahaan'),
            'use_title_placeholder' => $title === '',
            'use_tagline_placeholder' => $tagline === '',
        ];
    }

    /**
     * @return array{
     *     address: string|null,
     *     phone: string|null,
     *     address_placeholder: string,
     *     phone_placeholder: string,
     *     use_address_placeholder: bool,
     *     use_phone_placeholder: bool
     * }
     */
    public function companyContact(): array
    {
        $placeholders = config('pdf.placeholders', []);
        $address = trim((string) config('erp.pdf_company_address', ''));
        $phone = trim((string) config('erp.pdf_company_phone', ''));

        return [
            'address' => $address !== '' ? $address : null,
            'phone' => $phone !== '' ? $phone : null,
            'address_placeholder' => (string) ($placeholders['address'] ?? 'Alamat perusahaan'),
            'phone_placeholder' => (string) ($placeholders['phone'] ?? 'Nomor telepon'),
            'use_address_placeholder' => $address === '',
            'use_phone_placeholder' => $phone === '',
        ];
    }
}
