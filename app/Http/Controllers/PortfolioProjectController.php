<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PortfolioProjectController extends Controller
{
    public function __invoke(Request $request, PortfolioProject $project): Response
    {
        $canViewEvidence = $request->user()?->hasVerifiedEmail() && Gate::allows('view', $project);
        abort_unless($project->is_published || $canViewEvidence, 404);
        $project->load('student:id,name');

        return response()->view('pages.portfolios.show', [
            'portfolio' => [
                'title' => $project->title,
                'description' => $project->description,
                'author_name' => $project->student->name,
                'skills' => $project->skills,
                'project_url' => $project->project_url,
                'completed_on' => $project->completed_on?->format('d M Y'),
                'is_published' => $project->is_published,
            ],
            'evidenceUrl' => $canViewEvidence && $project->evidence_path ? route('portfolios.evidence', $project) : null,
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
