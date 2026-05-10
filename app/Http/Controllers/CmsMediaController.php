<?php

namespace App\Http\Controllers;

use App\Models\CmsMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CmsMediaController extends Controller
{
    public function index(): Response
    {
        $items = CmsMedia::query()
            ->latest()
            ->limit(300)
            ->get()
            ->map(fn (CmsMedia $m) => [
                'id' => $m->id,
                'original_name' => $m->original_name,
                'mime' => $m->mime,
                'size_bytes' => (int) $m->size_bytes,
                'alt_text' => $m->alt_text,
                'url' => $m->adminLibraryUrl(),
                'public_url' => $m->publicUrl(),
                'created_at' => $m->created_at?->toIso8601String(),
            ]);

        return Inertia::render('CMS/Media/Index', [
            'media' => $items,
        ]);
    }

    public function file(CmsMedia $cmsMedia): BinaryFileResponse
    {
        if ($cmsMedia->disk !== 'public') {
            abort(404);
        }

        if (! Storage::disk('public')->exists($cmsMedia->path)) {
            abort(404);
        }

        $absolute = Storage::disk('public')->path($cmsMedia->path);

        $filename = str_replace(['"', "\r", "\n"], '', (string) $cmsMedia->original_name) ?: 'file';

        return response()->file($absolute, [
            'Content-Type' => $cmsMedia->mime ?: (mime_content_type($absolute) ?: 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,webp,gif,pdf',
            'alt_text' => 'nullable|string|max:255',
        ]);

        $upload = $request->file('file');
        $path = $upload->store('cms-media', 'public');

        CmsMedia::query()->create([
            'user_id' => $request->user()?->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $upload->getClientOriginalName(),
            'mime' => $upload->getClientMimeType(),
            'size_bytes' => $upload->getSize() ?: 0,
            'alt_text' => isset($validated['alt_text']) ? trim((string) $validated['alt_text']) ?: null : null,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Media berhasil diunggah.']);
    }

    public function destroy(Request $request, CmsMedia $cmsMedia): RedirectResponse
    {
        if ($cmsMedia->disk && $cmsMedia->path) {
            Storage::disk($cmsMedia->disk)->delete($cmsMedia->path);
        }
        $cmsMedia->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Media dihapus.']);
    }
}
