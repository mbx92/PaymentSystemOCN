<?php

namespace App\Http\Controllers;

use App\Models\LabelProfile;
use App\Services\LanTsplPrinter;
use App\Services\WindowsSmbRawPrinter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LabelProfileController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('erp.admin.printer-and-label', ['tab' => 'label-profiles']);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'width_mm' => 'required|numeric|min:10|max:500',
            'height_mm' => 'required|numeric|min:10|max:500',
            'dpi' => 'required|integer|in:203,300,600',
            'margin_left_mm' => 'required|numeric|min:0|max:50',
            'margin_top_mm' => 'required|numeric|min:0|max:50',
            'gap_mm' => 'required|numeric|min:0|max:30',
            'rows' => 'required|integer|min:1|max:3',
            'protocol' => 'required|string|in:zpl,epl,tspl',
            'barcode_type' => 'required|string|in:code128,ean13,code39',
            'barcode_width' => 'required|integer|min:1|max:3',
        ]);

        LabelProfile::query()->create($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Profil label berhasil ditambahkan.']);
    }

    public function update(Request $request, LabelProfile $labelProfile): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'width_mm' => 'required|numeric|min:10|max:500',
            'height_mm' => 'required|numeric|min:10|max:500',
            'dpi' => 'required|integer|in:203,300,600',
            'margin_left_mm' => 'required|numeric|min:0|max:50',
            'margin_top_mm' => 'required|numeric|min:0|max:50',
            'gap_mm' => 'required|numeric|min:0|max:30',
            'rows' => 'required|integer|min:1|max:3',
            'protocol' => 'required|string|in:zpl,epl,tspl',
            'barcode_type' => 'required|string|in:code128,ean13,code39',
            'barcode_width' => 'required|integer|min:1|max:3',
        ]);

        $labelProfile->update($validated);

        return back()->with('flash', ['type' => 'success', 'message' => 'Profil label berhasil diperbarui.']);
    }

    public function simulation(LabelProfile $labelProfile, WindowsSmbRawPrinter $smb, LanTsplPrinter $tspl): JsonResponse
    {
        return response()->json([
            'profile' => [
                'id' => $labelProfile->id,
                'name' => $labelProfile->name,
                'width_mm' => (float) $labelProfile->width_mm,
                'height_mm' => (float) $labelProfile->height_mm,
                'dpi' => (int) $labelProfile->dpi,
                'margin_left_mm' => (float) $labelProfile->margin_left_mm,
                'margin_top_mm' => (float) $labelProfile->margin_top_mm,
                'gap_mm' => (float) $labelProfile->gap_mm,
                'rows' => (int) $labelProfile->rows,
                'protocol' => $labelProfile->protocol,
                'barcode_type' => $labelProfile->barcodeType(),
                'barcode_width' => $labelProfile->barcodeWidth(),
            ],
            'simulation' => [
                'native_protocol' => strtoupper((string) $labelProfile->protocol),
                'native_payload' => $smb->samplePayloadForProfile($labelProfile),
                'tspl_payload' => $tspl->buildSampleJob($labelProfile),
            ],
        ]);
    }

    public function destroy(LabelProfile $labelProfile): RedirectResponse
    {
        $labelProfile->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Profil label berhasil dihapus.']);
    }
}
