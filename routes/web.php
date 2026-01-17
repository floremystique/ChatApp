<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatListController;


Route::get('/', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Everything that needs login
Route::middleware('auth')->group(function () {

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

    Route::get('/chats', [ChatController::class, 'index'])->name('chats.index');
    Route::get('/chats/poll', [\App\Http\Controllers\ChatListController::class, 'poll'])->name('chats.poll');

    // use uuid binding
    Route::get('/chat/{room}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{room}/send', [ChatController::class, 'send'])->name('chat.send');
    Route::get('/chat/{room}/messages', [ChatController::class, 'messages'])->name('chat.messages');

    Route::post('/chat/{room}/typing', [ChatController::class, 'typing'])->name('chat.typing');
    Route::get('/chat/{room}/typing', [ChatController::class, 'typingStatus'])->name('chat.typingStatus');

    Route::get('/chat/{room}/seen-status', [ChatController::class, 'seenStatus'])->name('chat.seenStatus');
});

require __DIR__.'/auth.php';
