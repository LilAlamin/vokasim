<?php

namespace App\Http\Controllers;

use App\Models\PartnerAgreement;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PartnerAgreementDownloadController extends Controller
{
    public function __invoke(PartnerAgreement $agreement): BinaryFileResponse
    {
        Gate::authorize('view', $agreement);
        abort_unless($agreement->document_path && Storage::disk('local')->exists($agreement->document_path), 404);

        return response()->download(Storage::disk('local')->path($agreement->document_path), 'kerja-sama-'.$agreement->id.'.pdf', [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
