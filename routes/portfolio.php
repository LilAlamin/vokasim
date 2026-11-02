<?php

use App\Http\Controllers\CertificateFileController;
use App\Http\Controllers\CertificateQrController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\PortfolioEvidenceController;
use App\Http\Controllers\PortfolioProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('portofolio', 'pages::portfolios.index')->name('portfolios.index');
    Route::get('portofolio/{project}/bukti', PortfolioEvidenceController::class)->whereNumber('project')->name('portfolios.evidence');
    Route::livewire('sertifikat', 'pages::certificates.index')->name('certificates.index');
    Route::get('sertifikat/{certificate}/berkas', CertificateFileController::class)->whereNumber('certificate')->name('certificates.file');
    Route::get('sertifikat/{certificate}/qr', CertificateQrController::class)->whereNumber('certificate')->name('certificates.qr');
    Route::livewire('karier', 'pages::careers.index')->name('careers.index');
});

Route::get('portofolio/{project:public_id}', PortfolioProjectController::class)->whereUuid('project')->name('portfolios.show');
Route::get('verifikasi-sertifikat/{certificate:verification_token}', CertificateVerificationController::class)
    ->whereUuid('certificate')->middleware('throttle:60,1')->name('certificates.verify');
