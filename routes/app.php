<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OAuthController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\SocialAccountsController;
use App\Http\Controllers\StripePaymentController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authenticated app routes
|--------------------------------------------------------------------------
| Mounted under /app with the web + auth + verified stack (see web.php).
| These are the data + mutation + OAuth endpoints the Inertia app consumes.
| Some still return the old JSON envelope and are called via axios; page
| loads are progressively converted to Inertia::render (Phase 3/4 slices).
*/

// --- OAuth connect: per-provider redirect + callback (paths match config/services.php) ---
Route::prefix('connect')->group(function () {
    Route::get('notion/redirect', [OAuthController::class, 'notionAuth']);
    Route::get('facebook/redirect', [OAuthController::class, 'facebookAuth']);
    Route::get('linkedin/redirect', [OAuthController::class, 'linkedinAuth']);
    Route::get('linkedin-pro/redirect', [OAuthController::class, 'linkedinProAuth']);
    Route::get('twitter/redirect', [OAuthController::class, 'twitterAuth']);
    Route::get('tiktok/redirect', [OAuthController::class, 'tiktokAuth']);
    Route::get('threads/redirect', [OAuthController::class, 'threadsAuth']);
    Route::get('youtube/redirect', [OAuthController::class, 'youtubeAuth']);

    Route::get('notion/callback', [OAuthController::class, 'handleNotionCallback']);
    Route::get('facebook/callback', [OAuthController::class, 'handleFacebookCallback']);
    Route::get('linkedin/callback', [OAuthController::class, 'handleLinkedinCallback']);
    Route::get('linkedin-pro/callback', [OAuthController::class, 'handleLinkedinProCallback']);
    Route::get('twitter/callback', [OAuthController::class, 'handleTwitterCallback']);
    Route::get('tiktok/callback', [OAuthController::class, 'handleTiktokCallback']);
    Route::get('threads/callback', [OAuthController::class, 'handleThreadsCallback']);
    Route::get('youtube/callback', [OAuthController::class, 'handleYoutubeCallback']);
});

// --- Social accounts ---
Route::get('socials/all', [SocialAccountsController::class, 'getAllSocialAccounts']);
Route::post('socials/remove', [DashboardController::class, 'deleteSocialAccount']);

// --- Notion databases ---
Route::get('databases/connectionsAndSocials', [DashboardController::class, 'getConnections']);
Route::get('databases/scanForNew', [DashboardController::class, 'lookForNewDatabases']);
Route::post('databases/buildScaffolding', [DashboardController::class, 'buildDatabaseScaffolding']);
Route::post('databases/editSocials', [DashboardController::class, 'updateDatabaseSocials']);
Route::post('databases/remove', [DashboardController::class, 'removeDatabase']);
Route::post('databases/reconnect', [DashboardController::class, 'reconnectDatabase']);

// --- Notion pages ---
Route::get('pages/scanAll', [DashboardController::class, 'lookForPages']);
Route::post('pages/buildScaffolding', [DashboardController::class, 'buildPageScaffolding']);

// --- Posts ---
Route::get('posts/all', [DashboardController::class, 'getAllPosts']);
Route::get('posts/submitted', [DashboardController::class, 'submittedPosts']);
Route::post('post/remove', [DashboardController::class, 'deletePost']);
Route::post('post/reschedule', [DashboardController::class, 'reschedulePost']);

// --- User account ---
Route::get('user/finishedWizard', function () {
    $user = auth()->user();
    $user->completed_wizard = true;
    $user->save();

    return $user;
});
Route::post('user/password/edit', [UserController::class, 'changePassword']);
Route::post('user/email/edit', [UserController::class, 'changeEmail']);
Route::post('user/delete', [UserController::class, 'deleteAccount']);
Route::get('user/analytics', [UserController::class, 'getAnalytics']);
Route::post('user/support', [UserController::class, 'getSupport']);
Route::get('user/billing/portal', [UserController::class, 'generateBillingPortalUrl']);

// --- Billing / packages ---
Route::get('pricing', [StripePaymentController::class, 'page'])->name('pricing');
Route::get('packages', [StripePaymentController::class, 'returnPackages']);
Route::get('packagesAndDiscounts', [StripePaymentController::class, 'returnPackagesAndDiscounts']);
Route::post('purchase', [StripePaymentController::class, 'generatePayment']);

// --- Support ---
Route::inertia('support', 'app/Support')->name('support');

// --- Onboarding wizard ---
Route::get('setup', [SetupController::class, 'index'])->name('setup');

// --- Affiliates ---
Route::get('affiliates', [AffiliateController::class, 'index'])->name('affiliates');

// --- Admin debug utilities (admin only) ---
Route::middleware(IsAdmin::class)->prefix('admin')->group(function () {
    Route::get('facebookDebugToken/{token?}', [AdminController::class, 'facebookDebugToken']);
    Route::get('debugPost/{postid?}', [AdminController::class, 'debugPost']);
    Route::get('debugDatabase/{databaseid?}', [AdminController::class, 'debugDatabase']);
    Route::get('debugUser/{userId?}', [AdminController::class, 'debugUser']);
    Route::get('debugExternalPost/{userId}/{dbId}/{postId}', [AdminController::class, 'debugExternalPost']);
});
