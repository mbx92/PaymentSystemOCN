<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|uuid|exists:projects,id',
            'referrer_name' => 'required|string|max:255',
            'commission_amount' => 'required|numeric|min:0',
            'paid_at' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        Referral::create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Referral berhasil ditambahkan.']);
    }

    public function update(Request $request, Referral $referral)
    {
        $validated = $request->validate([
            'referrer_name' => 'required|string|max:255',
            'commission_amount' => 'required|numeric|min:0',
            'paid_at' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $referral->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Referral berhasil diperbarui.']);
    }

    public function destroy(Referral $referral)
    {
        $referral->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Referral berhasil dihapus.']);
    }
}
