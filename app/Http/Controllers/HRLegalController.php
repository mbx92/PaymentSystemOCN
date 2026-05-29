<?php

namespace App\Http\Controllers;

use App\Support\LegalVaultPath;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class HRLegalController extends Controller
{
    private const ROOT_SEGMENT = 'legal-vault';

    public function index(Request $request): Response
    {
        $relative = $this->normalizeRelativePath($request->string('path')->toString());
        $absolute = $this->absolutePath($relative);

        abort_unless(File::isDirectory($absolute), 404);

        $folders = [];
        $files = [];

        foreach (File::directories($absolute) as $dirPath) {
            $name = basename($dirPath);
            $childRel = $relative === '' ? $name : $relative.'/'.$name;
            $folders[] = [
                'name' => $name,
                'path' => $childRel,
            ];
        }
        foreach (File::files($absolute) as $splFileInfo) {
            $name = $splFileInfo->getFilename();
            $childRel = $relative === '' ? $name : $relative.'/'.$name;
            $ext = strtolower($splFileInfo->getExtension());
            $files[] = [
                'name' => $name,
                'path' => $childRel,
                'size_kb' => round($splFileInfo->getSize() / 1024, 1),
                'modified_at' => date('Y-m-d H:i', $splFileInfo->getMTime()),
                'is_pdf' => $ext === 'pdf',
                'download_url' => route('erp.hr.legal.files.download', ['path' => $childRel]),
                'view_url' => $ext === 'pdf'
                    ? route('erp.hr.legal.files.view', ['path' => $childRel])
                    : null,
            ];
        }
        usort($folders, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return Inertia::render('ERP/HR/Legal', [
            'currentPath' => $relative,
            'breadcrumbs' => $this->breadcrumbs($relative),
            'folders' => $folders,
            'files' => $files,
        ]);
    }

    public function storeFolder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:2000',
            'name' => 'required|string|max:120',
        ]);

        $parent = $this->normalizeRelativePath((string) ($validated['path'] ?? ''));
        $folderName = $this->sanitizeSingleName($validated['name']);
        $newRel = $parent === '' ? $folderName : $parent.'/'.$folderName;
        $target = $this->absolutePath($newRel);

        if (File::exists($target)) {
            return back()->withErrors(['name' => 'Folder sudah ada.'])->withInput();
        }

        File::makeDirectory($target, 0755, true);

        return redirect()->route('erp.hr.legal', ['path' => $parent])
            ->with('flash', ['type' => 'success', 'message' => 'Folder berhasil dibuat.']);
    }

    public function upload(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => 'nullable|string|max:2000',
            'file' => 'required|file|max:51200|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,zip,rar',
        ]);

        $parent = $this->normalizeRelativePath((string) ($validated['path'] ?? ''));
        $dir = $this->absolutePath($parent);

        abort_unless(File::isDirectory($dir), 404);

        $upload = $request->file('file');
        $original = $this->sanitizeFileName($upload->getClientOriginalName());
        $destRel = $parent === '' ? $original : $parent.'/'.$original;
        $destAbs = $this->absolutePath($destRel);

        // Simpan nama file sama dengan aslinya: timpa file lama jika sudah ada.
        if (File::exists($destAbs) && File::isFile($destAbs)) {
            File::delete($destAbs);
        }

        $upload->move(dirname($destAbs), basename($destAbs));

        return redirect()->route('erp.hr.legal', ['path' => $parent])
            ->with('flash', ['type' => 'success', 'message' => 'File berhasil diunggah.']);
    }

    public function destroyItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:2000',
            'type' => 'required|in:folder,file',
        ]);

        $rel = $this->normalizeRelativePath($validated['path']);
        abort_if($rel === '', 422, 'Tidak dapat menghapus root.');

        $abs = $this->absolutePath($rel);
        abort_unless(File::exists($abs), 404);

        if ($validated['type'] === 'folder') {
            abort_unless(File::isDirectory($abs), 422, 'Path bukan folder.');

            File::deleteDirectory($abs);
        } else {
            abort_unless(File::isFile($abs), 422, 'Path bukan file.');

            File::delete($abs);

            $parentAbs = dirname($abs);
            if (File::isDirectory($parentAbs) && count(File::files($parentAbs)) === 0 && count(File::directories($parentAbs)) === 0) {
                File::deleteDirectory($parentAbs);
            }
        }

        return redirect()->route('erp.hr.legal', ['path' => $parent])
            ->with('flash', ['type' => 'success', 'message' => 'Item berhasil dihapus.']);
    }

    public function downloadFile(Request $request): BinaryFileResponse
    {
        if (! $request->hasValidSignature()) {
            abort(401, 'Link download tidak valid atau sudah kedaluwarsa.');
        }

        $rel = $this->normalizeRelativePath($request->string('path')->toString());
        $abs = $this->absolutePath($rel);
        abort_unless(File::isFile($abs), 404);

        return response()->download($abs, basename($abs));
    }

    public function viewFile(Request $request): BinaryFileResponse
    {
        $rel = $this->normalizeRelativePath($request->string('path')->toString());
        $abs = $this->absolutePath($rel);
        abort_unless(File::isFile($abs), 404);
        abort_unless(strtolower(pathinfo($abs, PATHINFO_EXTENSION)) === 'pdf', 415);

        return response()->file($abs, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.basename($abs).'"',
        ]);
    }

    public function downloadTemplate(string $file): BinaryFileResponse
    {
        $safeName = basename($file);
        $path = base_path('docs/'.$safeName);

        abort_unless(File::exists($path), 404);
        abort_unless(in_array(strtolower(pathinfo($safeName, PATHINFO_EXTENSION)), ['doc', 'docx'], true), 404);

        return response()->download($path, $safeName);
    }

    private function vaultRoot(): string
    {
        $root = storage_path('app/'.self::ROOT_SEGMENT);
        if (! File::isDirectory($root)) {
            File::makeDirectory($root, 0755, true);
        }

        return $root;
    }

    private function normalizeRelativePath(string $path): string
    {
        try {
            return LegalVaultPath::normalize($path);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }
    }

    private function isSafePathSegment(string $segment): bool
    {
        return LegalVaultPath::isSafeSegment($segment);
    }

    private function sanitizeSingleName(string $name): string
    {
        $name = trim($name);
        $name = str_replace(['/', '\\'], '', $name);
        abort_if($name === '', 422, 'Nama folder tidak valid.');
        abort_unless($this->isSafePathSegment($name), 422, 'Nama folder tidak valid.');

        return $name;
    }

    private function sanitizeFileName(string $name): string
    {
        $name = basename($name);
        $name = trim($name);
        abort_if($name === '', 422, 'Nama file tidak valid.');
        abort_unless($this->isSafePathSegment($name), 422, 'Nama file tidak valid.');

        return $name;
    }

    private function absolutePath(string $relative): string
    {
        $root = realpath($this->vaultRoot());
        abort_if($root === false, 500, 'Legal vault tidak tersedia.');

        if ($relative === '') {
            return $root;
        }

        $built = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $resolved = realpath($built);

        if ($resolved !== false) {
            $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
            $targetNorm = rtrim(str_replace('\\', '/', $resolved), '/');
            abort_unless(str_starts_with($targetNorm, $rootNorm.'/') || $targetNorm === $rootNorm, 403);

            return $resolved;
        }

        $rootNorm = rtrim(str_replace('\\', '/', $root), '/');
        $builtNorm = str_replace('\\', '/', $built);
        abort_unless(str_starts_with($builtNorm, $rootNorm.'/'), 403);

        return $built;
    }

    /**
     * @return array<int, array{name: string, path: string}>
     */
    private function breadcrumbs(string $relative): array
    {
        $crumbs = [
            ['name' => 'Legal', 'path' => ''],
        ];
        if ($relative === '') {
            return $crumbs;
        }

        $acc = [];
        foreach (explode('/', $relative) as $part) {
            $acc[] = $part;
            $crumbs[] = [
                'name' => $part,
                'path' => implode('/', $acc),
            ];
        }

        return $crumbs;
    }
}
