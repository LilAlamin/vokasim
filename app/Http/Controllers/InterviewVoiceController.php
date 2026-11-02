<?php

namespace App\Http\Controllers;

use App\Actions\AiInterviewClient;
use App\Models\InterviewSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class InterviewVoiceController extends Controller
{
    public function __invoke(Request $request, InterviewSession $session, AiInterviewClient $client): Response
    {
        Gate::authorize('view', $session);
        Gate::authorize('update', $session);
        $question = $session->currentQuestion();
        abort_unless($session->interaction_mode !== 'text' && $question?->opened_at, 422);
        $data = $request->validate(['sdp' => ['required', 'string', 'max:65536', 'starts_with:v=0']]);
        $key = 'interview-voice:'.$session->id.':'.$question->id;
        if (RateLimiter::tooManyAttempts($key, 2)) {
            throw ValidationException::withMessages(['voice' => 'Batas pemutaran suara tercapai. Bacalah pertanyaan di layar.']);
        }
        RateLimiter::hit($key, 3600);
        try {
            return response($client->voice($session, $data['sdp']), 200, ['Content-Type' => 'application/sdp', 'Cache-Control' => 'no-store']);
        } catch (Throwable) {
            throw ValidationException::withMessages(['voice' => 'Suara AI belum tersedia. Pertanyaan tetap dapat dibaca di layar.']);
        }
    }
}
