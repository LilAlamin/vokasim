<?php

namespace App\Http\Controllers;

use App\Models\InternshipLogbook;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogbookPhotoController extends Controller
{
    public function __invoke(InternshipLogbook $logbook): BinaryFileResponse
    {
        Gate::authorize('view', $logbook);
        abort_unless($logbook->photo_path && Storage::disk('local')->exists($logbook->photo_path), 404);

        return response()->file(Storage::disk('local')->path($logbook->photo_path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
