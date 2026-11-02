<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Services\PortfolioFiles;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortfolioEvidenceController extends Controller
{
    public function __invoke(PortfolioProject $project, PortfolioFiles $files): StreamedResponse
    {
        Gate::authorize('view', $project);
        abort_unless($project->evidence_path && $project->evidence_mime, 404);

        return $files->download($project->evidence_path, 'bukti-proyek-'.$project->id, $project->evidence_mime);
    }
}
