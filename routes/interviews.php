<?php

use App\Http\Controllers\InterviewMediaController;
use App\Http\Controllers\InterviewVoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('wawancara', 'pages::interviews.index')->name('interviews.index');
    Route::livewire('wawancara/{session}', 'pages::interviews.session')->name('interviews.session');
    Route::post('wawancara/{session}/suara', InterviewVoiceController::class)->middleware('throttle:6,1')->name('interviews.voice');
    Route::get('wawancara-media/{question}/{kind}', InterviewMediaController::class)->whereIn('kind', ['audio', 'video'])->name('interviews.media');
});
