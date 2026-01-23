<x-guest-layout>

    <div class="auth-bg flex items-center justify-center px-4 py-10">
        <div class="blob b1"></div>
        <div class="blob b2"></div>
        <div class="blob b3"></div>

        <div class="w-full max-w-md pop-in">
            <div class="glass-card rounded-2xl p-7 md:p-8">
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center gap-3">
                        
                        <div>
                            <div class="text-dark text-lg font-semibold leading-tight">Welcome to JooJo</div>
                            <div class="text-dark/70 text-sm">Log in to continue</div>
                        </div>
                    </div>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4 text-dark/90" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf

                    <!-- Email -->
                    <div>
                        <x-input-label for="email" :value="__('Email')" class="text-dark/80" />
                        <x-text-input
                            id="email"
                            class="auth-input block mt-1 w-full rounded-xl"
                            type="email"
                            name="email"
                            :value="old('email')"
                            required
                            autofocus
                            autocomplete="username"
                            placeholder="you@example.com"
                        />
                        <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-200" />
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" :value="__('Password')" class="text-dark/80" />
                        <x-text-input
                            id="password"
                            class="auth-input block mt-1 w-full rounded-xl"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="••••••••"
                        />
                        <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-200" />
                    </div>

                    <!-- Remember + Forgot -->
                    <div class="flex items-center justify-between pt-1">
                        <label for="remember_me" class="inline-flex items-center gap-2">
                            <input id="remember_me" type="checkbox"
                                class="rounded border-white/20 bg-white/10 text-purple-600 shadow-sm focus:ring-purple-500"
                                name="remember">
                            <span class="text-sm text-dark/75">{{ __('Remember me') }}</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}"
                               class="text-sm text-dark/80 hover:text-dark underline underline-offset-4">
                                {{ __('Forgot?') }}
                            </a>
                        @endif
                    </div>

                    <!-- Buttons -->
                    <div class="pt-2 space-y-3">
                        <button type="submit"
                                class="w-full h-11 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-semibold transition active:scale-[0.99]">
                            {{ __('Log in') }}
                        </button>

                        <a href="{{ route('register') }}"
                           class="w-full h-11 rounded-xl border border-white/15 bg-white/5 hover:bg-white/10
                                  text-dark/90 font-semibold transition flex items-center justify-center">
                            {{ __('Create account') }}
                        </a>
                    </div>
                </form>

                <!-- Tiny footer -->
                <div class="mt-6 text-center text-xs text-dark/55">
                    By continuing, you agree to our terms & privacy policy.
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
