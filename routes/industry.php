<?php

use App\Http\Controllers\InternshipLeaveEvidenceController;
use App\Http\Controllers\PartnerAgreementDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('mitra', 'pages::partners.index')->name('partners.index');
    Route::get('mitra/perjanjian/{agreement}/unduh', PartnerAgreementDownloadController::class)->name('partners.agreements.download');
    Route::livewire('magang/izin', 'pages::internships.leaves')->name('internships.leaves');
    Route::get('magang/izin/{leave}/bukti', InternshipLeaveEvidenceController::class)->name('internships.leaves.evidence');
    Route::livewire('magang/evaluasi', 'pages::internships.evaluations')->name('internships.evaluations');
    Route::livewire('magang/rekap', 'pages::industry.index')->name('industry.index');
    Route::livewire('tracer-study', 'pages::tracer.index')->name('tracer.index');
    Route::livewire('tracer-study/survei', 'pages::tracer.survey')->name('tracer.survey');
});
