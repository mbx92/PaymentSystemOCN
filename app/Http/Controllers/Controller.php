<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    private const ALLOWED_PER_PAGE = [25, 50, 75, 100, 125, 150, 175, 200, 225, 250];

    protected function resolvedPerPage(Request $request): int
    {
        $perPage = (int) $request->query('per_page', 25);

        return in_array($perPage, self::ALLOWED_PER_PAGE, true) ? $perPage : 25;
    }

    /**
     * @param  list<string>  $only
     * @return array<string, mixed>
     */
    protected function filtersWithPerPage(Request $request, array $only): array
    {
        return array_merge($request->only($only), ['per_page' => $this->resolvedPerPage($request)]);
    }
}
