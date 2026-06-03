<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-input
                name="email"
                label="{{ __('Email address') }}"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
                inline
            />

            <div>
                <x-input
                    name="password"
                    label="{{ __('Password') }}"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="{{ __('Password') }}"
                    inline
                />

                @if (Route::has('password.request'))
                    <div class="flex justify-end mt-1">
                        <a href="{{ route('password.request') }}" class="text-sm link link-primary" wire:navigate>
                            {{ __('Forgot your password?') }}
                        </a>
                    </div>
                @endif
            </div>

            <x-checkbox name="remember" label="{{ __('Remember me') }}" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <x-button label="{{ __('Log in') }}" type="submit" class="btn-primary w-full" data-test="login-button" />
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-base-content/60">
            <span>{{ __('Don\'t have an account?') }}</span>
            <a href="{{ route('register') }}" class="link link-primary" wire:navigate>{{ __('Sign up') }}</a>
        </div>
    </div>
</x-layouts::auth>
