<?php

namespace App\Http\Controllers;

use App\Models\PracticumSubmission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PracticumSubmissionDownloadController extends Controller
{
    public function __invoke(PracticumSubmission $submission): BinaryFileResponse
    {
        Gate::authorize('view', $submission);
        abort_unless(Storage::disk('local')->exists($submission->file_path), 404);

        return response()->download(Storage::disk('local')->path($submission->file_path), Str::ascii(basename($submission->original_name)), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
