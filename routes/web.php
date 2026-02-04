<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\SpaController;

use App\Events\TestBroadcastNow;

Route::get('/', function () {
    return redirect()->route('spa');
})->middleware(['auth', 'verified'])->name('dashboard');

// Everything that needs login
Route::middleware('auth')->group(function () {

    // SPA shell (no page refresh)
    Route::get('/app', [SpaController::class, 'index'])->name('spa');
    Route::get('/app/{any}', [SpaController::class, 'index'])->where('any', '.*');

    // SPA bootstrap data
    Route::get('/api/bootstrap', [SpaController::class, 'bootstrap'])->name('api.bootstrap');
    Route::get("/api/rooms", [SpaController::class, "rooms"])->name("api.rooms");
    // SPA partials (HTML snippets, no layout)
    Route::get('/partials/matches', [MatchController::class, 'index'])->name('partials.matches');
    Route::get('/partials/profile', [ProfileController::class, 'edit'])->name('partials.profile');

    Route::get('/partials/onboarding-profile', [OnboardingController::class, 'create'])->name('partials.onboarding.profile');
    Route::get('/partials/onboarding-quiz', [OnboardingController::class, 'quiz'])->name('partials.onboarding.quiz');

    // Breeze profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Onboarding
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding');
    Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

    // Match
    Route::get('/match', [MatchController::class, 'index'])->name('match');
    Route::post('/match/start/{user}', [MatchController::class, 'start'])->name('match.start');

    // SPA deep-links (so refresh doesn't break for SPA chat URLs)
    // IMPORTANT: Keep these narrow so they don't swallow API routes like /chat/{uuid}/messages.
    Route::get('/chats', [SpaController::class, 'index']);
    Route::get('/chat/{room:uuid}', [SpaController::class, 'index']);

    // Chat APIs (UUID binding)
    Route::post('/chat/{room:uuid}/send', [ChatController::class, 'send'])->middleware('throttle:60,1')->name('chat.send');
    Route::get('/chat/{room:uuid}/messages', [ChatController::class, 'messages'])->middleware('throttle:120,1')->name('chat.messages');

    Route::post('/chat/{room:uuid}/typing', [ChatController::class, 'typing'])->middleware('throttle:240,1')->name('chat.typing');

    Route::post('/chat/{room:uuid}/seen', [ChatController::class, 'seen'])->middleware('throttle:180,1')->name('chat.seen');

    // Message actions
    Route::post('/chat/{room:uuid}/message/{message}/react', [ChatController::class, 'toggleHeart'])->middleware('throttle:60,1')->name('chat.message.heart');
    Route::delete('/chat/{room:uuid}/message/{message}', [ChatController::class, 'deleteMessage'])->middleware('throttle:60,1')->name('chat.message.delete');

    // Delete/close chat
    Route::post('/chat/{room:uuid}/delete-chat', [ChatController::class, 'deleteChat'])->name('chat.delete');

    Route::get('/onboarding/quiz', [OnboardingController::class, 'quiz'])->name('onboarding.quiz');
    Route::post('/onboarding/quiz', [OnboardingController::class, 'quizStore'])->name('onboarding.quiz.store');

});

Route::middleware(['auth','localonly'])->group(function () {
    Route::get('/broadcast-test', function () {
        return view('broadcast-test');
    });

    Route::post('/broadcast-test/fire', function (Request $request) {
        event(new TestBroadcastNow());
        return response()->json(['ok' => true]);
    });

    Route::get('/__broadcast-debug', function () {
        return response()->json([
            'env' => app()->environment(),
            // Intentionally limited; do not expose secrets.
            'broadcast_default' => config('broadcasting.default'),
            'reverb_host' => config('broadcasting.connections.reverb.options.host'),
            'reverb_port' => config('broadcasting.connections.reverb.options.port'),
        ]);
    });
});

require __DIR__.'/auth.php';