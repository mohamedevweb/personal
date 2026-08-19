<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ContentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\InstagramConnectionController;
use App\Http\Controllers\MomentController;
use App\Http\Controllers\OpportunityController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RemixController;
use App\Http\Controllers\SavedContentController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->prefix('auth')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::middleware('auth:sanctum')->prefix('integrations/instagram')->group(function (): void {
    Route::get('/authorize', [InstagramConnectionController::class, 'authorize']);
    Route::get('/status', [InstagramConnectionController::class, 'status']);
    Route::post('/sync', [InstagramConnectionController::class, 'sync']);
    Route::delete('/', [InstagramConnectionController::class, 'disconnect']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me/profile', [ProfileController::class, 'show']);
    Route::patch('/me/profile', [ProfileController::class, 'update']);
    Route::get('/feed', [FeedController::class, 'index']);
    Route::post('/feed/refresh', [FeedController::class, 'refresh']);
    Route::get('/content/{content}', [ContentController::class, 'show']);
    Route::post('/content/{content}/save', [ContentController::class, 'save']);
    Route::post('/content/{content}/dismiss', [ContentController::class, 'dismiss']);
    Route::get('/moments', [MomentController::class, 'index']);
    Route::post('/moments', [MomentController::class, 'store']);
    Route::patch('/moments/{moment}', [MomentController::class, 'update']);
    Route::delete('/moments/{moment}', [MomentController::class, 'destroy']);
    Route::get('/opportunities', OpportunityController::class);
    Route::get('/saved', SavedContentController::class);
    Route::get('/remixes/{remix}', [RemixController::class, 'show']);
    Route::patch('/remixes/{remix}', [RemixController::class, 'update']);
});

// Generation calls a language model, so it gets a tighter budget than the rest
// of the authenticated API.
Route::middleware(['auth:sanctum', 'throttle:generation'])->group(function (): void {
    Route::post('/content/{content}/remix', [ContentController::class, 'remix']);
    Route::post('/moments/{moment}/create-content', [MomentController::class, 'createContent']);
});

if (app()->isLocal() && config('app.enable_dev_session')) {
    Route::get('/development/session', function () {
        $user = User::query()->firstOrCreate(
            ['email' => 'creator@personal.local'],
            ['name' => 'Creator', 'password' => Hash::make(str()->random())],
        );

        $user->tokens()->where('name', 'local-development')->delete();

        return response()->json([
            'user' => $user,
            'token' => $user->createToken('local-development')->plainTextToken,
        ]);
    })->middleware('throttle:auth');
}
