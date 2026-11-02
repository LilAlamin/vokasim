<?php

namespace App\Http\Controllers;

use App\Models\InternshipLeaveRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InternshipLeaveEvidenceController extends Controller
{
    public function __invoke(InternshipLeaveRequest $leave): BinaryFileResponse
    {
        Gate::authorize('viewEvidence', $leave);
        abort_unless($leave->evidence_path && Storage::disk('local')->exists($leave->evidence_path), 404);

        return response()->download(Storage::disk('local')->path($leave->evidence_path), 'bukti-izin-'.$leave->id.'.'.pathinfo($leave->evidence_path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
