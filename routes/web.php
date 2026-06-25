<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OAuthController;
use Illuminate\Support\Facades\Route;

/*
| Inbound platform webhooks — server-to-server, no auth, CSRF-exempt
| (see bootstrap/app.php). External providers must point here at cutover.
*/
Route::prefix('webhooks')->group(function () {
    Route::post('tiktok', [OAuthController::class, 'handleTiktokWebhook']);
    Route::match(['get', 'post'], 'facebook', [OAuthController::class, 'handleFacebookWebhook']);
    Route::get('notion', [OAuthController::class, 'GEThandleNotionWebhook']);
    Route::post('notion', [OAuthController::class, 'handleNotionWebhook']);
    Route::post('threads', [OAuthController::class, 'handleThreadsWebhook']);
});

// The authenticated app lives under /app/* (auth routes are prefixed by Fortify).
Route::middleware(['auth', 'verified', 'wizard'])->prefix('app')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    require __DIR__.'/app.php';
});

require __DIR__.'/settings.php';

// Marketing landing (Blade). Registered last so its constrained catch-alls
// ({platform}, for/{useCase}) never shadow the app/auth/webhook routes above.
require __DIR__.'/landing.php';
