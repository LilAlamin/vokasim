<?php

namespace App\Http\Controllers;

use App\Models\InterviewQuestion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InterviewMediaController extends Controller
{
    public function __invoke(Request $request, InterviewQuestion $question, string $kind): BinaryFileResponse
    {
        Gate::authorize('view', $question->session);
        abort_unless(in_array($kind, ['audio', 'video'], true), 404);
        $path = $kind === 'audio' ? $question->audio_path : $question->video_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), [
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
