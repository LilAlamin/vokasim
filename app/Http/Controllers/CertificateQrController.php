<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CertificateQrController extends Controller
{
    public function __invoke(Certificate $certificate): Response
    {
        Gate::authorize('view', $certificate);
        abort_unless($certificate->isPubliclyVerifiable(), 404);
        $svg = (new Writer(new ImageRenderer(new RendererStyle(240, 4), new SvgImageBackEnd)))
            ->writeString(route('certificates.verify', ['certificate' => $certificate->verification_token]));

        return response($svg)->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
