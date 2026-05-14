<?php

namespace App\ERP\Core\Services;

use App\ERP\Core\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ErpCompanyResolver
{
    public const SESSION_KEY = 'erp_company_id';

    public static function isActiveCompany(int $id): bool
    {
        return Company::query()->whereKey($id)->where('is_active', true)->exists();
    }

    public static function defaultCompanyId(): ?int
    {
        return Company::query()->where('is_active', true)->orderBy('id')->value('id');
    }

    public static function currentCompanyIdForSession(Request $request): ?int
    {
        $sessionId = (int) ($request->session()->get(self::SESSION_KEY) ?? 0);
        if ($sessionId > 0 && self::isActiveCompany($sessionId)) {
            return $sessionId;
        }

        return self::defaultCompanyId();
    }

    /**
     * Untuk filter laporan: query ?company_id (valid) mengalahkan sesi.
     */
    public static function resolveForReporting(Request $request): ?int
    {
        $queryId = $request->query('company_id');
        if ($queryId !== null && $queryId !== '' && self::isActiveCompany((int) $queryId)) {
            return (int) $queryId;
        }

        return self::currentCompanyIdForSession($request);
    }

    /**
     * Untuk posting GL: body company_id (valid) mengalahkan sesi, lalu default.
     *
     * @throws ValidationException
     */
    public static function resolveForGlPosting(Request $request): int
    {
        $fromBody = $request->input('company_id');
        if ($fromBody !== null && $fromBody !== '') {
            $id = (int) $fromBody;
            if (self::isActiveCompany($id)) {
                return $id;
            }
            throw ValidationException::withMessages([
                'company_id' => 'Perusahaan tidak valid atau nonaktif.',
            ]);
        }

        $sessionId = (int) ($request->session()->get(self::SESSION_KEY) ?? 0);
        if ($sessionId > 0 && self::isActiveCompany($sessionId)) {
            return $sessionId;
        }

        $default = self::defaultCompanyId();
        if ($default) {
            return $default;
        }

        throw ValidationException::withMessages([
            'company_id' => 'Belum ada perusahaan aktif. Tambahkan perusahaan terlebih dahulu.',
        ]);
    }
}
