<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('notifikasi', 'pages::notifications.index')->name('notifications.index');
});
