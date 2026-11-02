<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\PortfolioFiles;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateFileController extends Controller
{
    public function __invoke(Certificate $certificate, PortfolioFiles $files): StreamedResponse
    {
        Gate::authorize('view', $certificate);

        return $files->download($certificate->file_path, 'sertifikat-'.$certificate->id, $certificate->file_mime);
    }
}
