<?php

namespace App\Providers;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Policies\ChatRoomPolicy;
use App\Policies\MessagePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Policy registration (Laravel 12 minimal provider setup)
        Gate::policy(ChatRoom::class, ChatRoomPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);

        if (env('FORCE_HTTPS') === 'true') {
            URL::forceScheme('https');
        }
    }
}
