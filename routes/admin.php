<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('admin/pengguna', 'pages::admin.users')->name('admin.users');
});
