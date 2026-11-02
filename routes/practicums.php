<?php

use App\Http\Controllers\PracticumMaterialController;
use App\Http\Controllers\PracticumSubmissionDownloadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('praktikum', 'pages::practicums.index')->name('practicums.index');
    Route::livewire('praktikum/{module}', 'pages::practicums.show')->name('practicums.show');
    Route::livewire('praktikum/{module}/tugas/{assignment}', 'pages::practicums.assignment')->name('practicums.assignments.show');
    Route::get('praktikum/materi/{material}/lihat', [PracticumMaterialController::class, 'show'])->name('practicums.materials.show');
    Route::get('praktikum/materi/{material}/unduh', [PracticumMaterialController::class, 'download'])->name('practicums.materials.download');
    Route::get('praktikum/laporan/{submission}/unduh', PracticumSubmissionDownloadController::class)->name('practicums.submissions.download');
});
