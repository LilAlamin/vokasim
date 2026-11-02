<?php

namespace App\Http\Controllers;

use App\Models\PracticumMaterial;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PracticumMaterialController extends Controller
{
    public function show(PracticumMaterial $material): BinaryFileResponse
    {
        Gate::authorize('view', $material);
        abort_unless(Storage::disk('local')->exists($material->file_path), 404);

        if (! $material->canPreview()) {
            return $this->download($material);
        }

        return response()->file(Storage::disk('local')->path($material->file_path), [
            'Content-Type' => $material->mime_type, 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox',
        ]);
    }

    public function download(PracticumMaterial $material): BinaryFileResponse
    {
        Gate::authorize('view', $material);
        abort_unless(Storage::disk('local')->exists($material->file_path), 404);

        return response()->download(Storage::disk('local')->path($material->file_path), Str::ascii(basename($material->original_name)), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
