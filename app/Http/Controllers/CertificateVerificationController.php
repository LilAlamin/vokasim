<?php

namespace App\Http\Controllers;

use App\Enums\CertificateStatus;
use App\Models\Certificate;
use Illuminate\Http\Response;

class CertificateVerificationController extends Controller
{
    public function __invoke(Certificate $certificate): Response
    {
        abort_unless($certificate->isPubliclyVerifiable(), 404);

        return response()->view('pages.certificates.verify', ['verification' => [
            'holder_name' => $certificate->holder_name,
            'title' => $certificate->title,
            'issuer' => $certificate->issuer,
            'issued_on' => $certificate->issued_on->format('d M Y'),
            'expires_on' => $certificate->expires_on?->format('d M Y'),
            'verified_on' => $certificate->verified_at?->format('d M Y'),
            'status' => $certificate->publicStatus(),
            'is_valid' => $certificate->status === CertificateStatus::Verified && ! $certificate->isExpired(),
        ]])->header('Cache-Control', 'no-store, private')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
