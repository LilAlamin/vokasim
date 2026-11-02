<?php

use App\Enums\UserRole;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LogbookPhotoController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::livewire('magang/penempatan', 'pages::internships.placements')->name('internships.placements');
    Route::livewire('magang/logbook', 'pages::internships.logbook')->name('internships.logbook');
    Route::livewire('magang/validasi', 'pages::internships.reviews')->name('internships.reviews');
    Route::get('magang/logbook/{logbook}/foto', LogbookPhotoController::class)->name('internships.photo');

    Route::post('switch-role/{role}', function (string $role): RedirectResponse {
        abort_unless(app()->environment('local'), 404);
        $validRoles = array_column(UserRole::cases(), 'value');
        abort_unless(in_array($role, $validRoles, true), 404);
        auth()->user()->update(['role' => $role]);

        return redirect()->route('dashboard');
    })->name('switch-role');
});

require __DIR__.'/settings.php';
require __DIR__.'/practicums.php';
require __DIR__.'/portfolio.php';
require __DIR__.'/industry.php';
require __DIR__.'/interviews.php';
require __DIR__.'/admin.php';
require __DIR__.'/notifications.php';
