<?php

use App\Http\Controllers\Admin\QueueDashboardController;
use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Content\ChatController;
use App\Http\Controllers\Content\ContentController;
use App\Http\Controllers\Content\FeedController;
use App\Http\Controllers\Content\OpportunityController;
use App\Http\Controllers\Content\RemixController;
use App\Http\Controllers\Content\SavedContentController;
use App\Http\Controllers\Creator\CreatorInspirationController;
use App\Http\Controllers\Creator\MomentController;
use App\Http\Controllers\Creator\ProfileController;
use App\Http\Controllers\Instagram\InstagramConnectionController;
use App\Http\Controllers\Instagram\MediaController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [PasswordResetController::class, 'requestLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'reset']);
});

Route::middleware('auth:sanctum')->prefix('auth')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Account management and email verification stay reachable while unverified: a
// creator has to be able to fix their address, change their password, or ask for
// a fresh link before they can clear the verification gate.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::patch('/me/account', [AccountController::class, 'update']);
    Route::put('/me/password', [AccountController::class, 'updatePassword']);
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'resend'])
        ->middleware('throttle:6,1');
});

// The signed link inside the verification email arrives without a token, so this
// route authenticates on the signature alone.
Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// Browsers cannot embed some Instagram CDN responses because they carry
// Cross-Origin-Resource-Policy: same-origin. These URLs are signed so the
// application remains a bounded media relay rather than an open proxy.
Route::middleware(['signed:relative', 'throttle:media'])->group(function (): void {
    Route::get('/media/content/{content}', [MediaController::class, 'content'])
        ->name('media.content');
    Route::get('/media/content/{content}/video', [MediaController::class, 'contentVideo'])
        ->name('media.content.video');
    Route::get('/media/content/{content}/{position}', [MediaController::class, 'contentItem'])
        ->whereNumber('position')
        ->name('media.content.item');
    Route::get('/media/creator/{creator}', [MediaController::class, 'creator'])
        ->name('media.creator');
    Route::get('/media/creator-profile/{profile}', [MediaController::class, 'creatorProfile'])
        ->name('media.creator-profile');
    Route::get('/media/creator-preview/{username}', [MediaController::class, 'creatorPreview'])
        ->where('username', '[A-Za-z0-9._]+')
        ->name('media.creator-preview');
    Route::get('/media/instagram-account/{account}', [MediaController::class, 'instagramAccount'])
        ->name('media.instagram-account');
});

Route::middleware(['auth:sanctum', 'verified'])->prefix('integrations/instagram')->group(function (): void {
    Route::get('/authorize', [InstagramConnectionController::class, 'authorize']);
    Route::get('/status', [InstagramConnectionController::class, 'status']);
    Route::get('/progress', [InstagramConnectionController::class, 'progress']);
    // Saving a handle now scrapes the public profile behind it, so it is bounded
    // like the other endpoints that reach a provider.
    Route::put('/handle', [InstagramConnectionController::class, 'storeHandle'])
        ->middleware('throttle:discovery');
    Route::post('/sync', [InstagramConnectionController::class, 'sync']);
    Route::delete('/', [InstagramConnectionController::class, 'disconnect']);
});

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::get('/me/profile', [ProfileController::class, 'show']);
    Route::patch('/me/profile', [ProfileController::class, 'update']);
    Route::get('/me/posts', [ProfileController::class, 'posts']);
    Route::get('/feed', [FeedController::class, 'index']);
    Route::get('/feed/global', [FeedController::class, 'global']);
    Route::get('/content/{content}', [ContentController::class, 'show']);
    Route::post('/content/{content}/save', [ContentController::class, 'save']);
    Route::post('/content/{content}/dismiss', [ContentController::class, 'dismiss']);
    Route::get('/moments', [MomentController::class, 'index']);
    Route::post('/moments', [MomentController::class, 'store']);
    Route::patch('/moments/{moment}', [MomentController::class, 'update']);
    Route::delete('/moments/{moment}', [MomentController::class, 'destroy']);
    Route::get('/opportunities', OpportunityController::class);
    Route::get('/saved', SavedContentController::class);
    Route::get('/remixes', [RemixController::class, 'index']);
    Route::get('/remixes/{remix}', [RemixController::class, 'show']);
    Route::patch('/remixes/{remix}', [RemixController::class, 'update']);
    Route::delete('/remixes/{remix}', [RemixController::class, 'destroy']);
    Route::post('/remixes/{remix}/copied', [RemixController::class, 'copied']);
    Route::get('/creator-inspirations', [CreatorInspirationController::class, 'index']);
    Route::put('/creator-inspirations', [CreatorInspirationController::class, 'update'])
        ->middleware('throttle:discovery');
});

// Operational metadata is private and deliberately separate from creator data.
Route::middleware(['auth:sanctum', 'verified', 'throttle:api'])->group(function (): void {
    Route::get('/admin/queues', QueueDashboardController::class);
});

Route::middleware(['auth:sanctum', 'verified', 'throttle:discovery'])->group(function (): void {
    Route::get('/creator-inspirations/search', [CreatorInspirationController::class, 'search']);
});

// Generation calls a language model, so it gets a tighter budget than the rest
// of the authenticated API.
Route::middleware(['auth:sanctum', 'verified', 'throttle:generation'])->group(function (): void {
    Route::post('/content/{content}/analysis', [ContentController::class, 'analyze']);
    Route::post('/content/{content}/remix', [ContentController::class, 'remix']);
    Route::post('/moments/{moment}/create-content', [MomentController::class, 'createContent']);
    Route::post('/remixes/{remix}/retry', [RemixController::class, 'retry']);
    Route::post('/remixes/{remix}/regenerate-block', [RemixController::class, 'regenerateBlock']);
    Route::post('/chat', ChatController::class);
});

if (app()->isLocal() && config('app.enable_dev_session')) {
    Route::get('/development/session', function () {
        $user = User::query()->firstOrCreate(
            ['email' => 'creator@personal.local'],
            ['name' => 'Creator', 'password' => Hash::make(str()->random()), 'email_verified_at' => now()],
        );

        $user->tokens()->where('name', 'local-development')->delete();

        $token = $user->createToken('local-development')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ])->cookie(cookie(
            'personal_token',
            $token,
            60 * 24 * 30,
            '/',
            config('session.domain'),
            config('session.secure') ?? app()->isProduction(),
            true,
            false,
            config('session.same_site', 'lax'),
        ));
    })->middleware('throttle:auth');
}
