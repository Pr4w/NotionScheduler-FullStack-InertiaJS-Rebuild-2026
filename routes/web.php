<?php

use Illuminate\Support\Facades\Route;

// TODO Phase 5: '/' is replaced by the Blade marketing landing (home2026).
Route::inertia('/', 'Welcome')->name('home');

// The authenticated app lives under /app/* (auth routes are prefixed by Fortify).
Route::middleware(['auth', 'verified'])->prefix('app')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
