<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;


use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatListController;
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

    // Chats
    Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
    Route::get('/chats/poll', [ChatListController::class, 'poll'])->name('chats.poll');

    // Chats (UUID binding)
    Route::get('/chat/{room:uuid}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{room:uuid}/send', [ChatController::class, 'send'])->name('chat.send');
    Route::get('/chat/{room:uuid}/messages', [ChatController::class, 'messages'])->name('chat.messages');

    Route::post('/chat/{room:uuid}/typing', [ChatController::class, 'typing'])->name('chat.typing');
    Route::get('/chat/{room:uuid}/typing', [ChatController::class, 'typingStatus'])->name('chat.typingStatus');

    Route::post('/chat/{room:uuid}/seen', [ChatController::class, 'seen'])->name('chat.seen');
    Route::get('/chat/{room:uuid}/seen-status', [ChatController::class, 'seenStatus'])->name('chat.seenStatus');

    // Message actions
    Route::post('/chat/{room:uuid}/message/{message}/react', [ChatController::class, 'toggleHeart'])->name('chat.message.heart');
    Route::delete('/chat/{room:uuid}/message/{message}', [ChatController::class, 'deleteMessage'])->name('chat.message.delete');

    // Delete/close chat
    Route::post('/chat/{room:uuid}/delete-chat', [ChatController::class, 'deleteChat'])->name('chat.delete');

    Route::get('/onboarding/quiz', [OnboardingController::class, 'quiz'])->name('onboarding.quiz');
    Route::post('/onboarding/quiz', [OnboardingController::class, 'quizStore'])->name('onboarding.quiz.store');

});

Route::get('/__dbg', function () {
    return response()->json([
        'reverb_apps' => config('reverb.apps'),
        'reverb_conn' => config('broadcasting.connections.reverb'),
    ]);
});

Route::get('/broadcast-test', function () {
    return view('broadcast-test');
})->middleware('auth');


Route::get('/__broadcast-debug', function () {
    return response()->json([
        'env' => app()->environment(),
        'broadcast_default' => config('broadcasting.default'),
        'reverb' => config('broadcasting.connections.reverb'),
        'pusher' => config('broadcasting.connections.pusher'),
    ]);
});

require __DIR__.'/auth.php';
