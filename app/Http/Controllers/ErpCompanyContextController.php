<?php

namespace App\Http\Controllers;

use App\ERP\Core\Services\ErpCompanyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ErpCompanyContextController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => [
                'required',
                'integer',
                Rule::exists('companies', 'id')->where('is_active', true),
            ],
        ]);

        $request->session()->put(ErpCompanyResolver::SESSION_KEY, (int) $validated['company_id']);

        return back();
    }
}
