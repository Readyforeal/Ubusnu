<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen font-sans antialiased bg-base-200/50">

        {{-- NAVBAR --}}
        <x-nav sticky full-width>
            <x-slot:brand>
                <x-app-logo />
            </x-slot:brand>

            <x-slot:actions>
                @if (Route::has('login'))
                    @auth
                        <x-button label="{{ __('Dashboard') }}" link="{{ route('dashboard') }}" class="btn-sm btn-ghost" wire:navigate />
                    @else
                        <x-button label="{{ __('Log in') }}" link="{{ route('login') }}" class="btn-sm btn-ghost" wire:navigate />

                        @if (Route::has('register'))
                            <x-button label="{{ __('Register') }}" link="{{ route('register') }}" class="btn-sm btn-primary" wire:navigate />
                        @endif
                    @endauth
                @endif
            </x-slot:actions>
        </x-nav>

        {{-- MAIN CONTENT --}}
        <x-main full-width>
            <x-slot:content>
                <div class="flex items-center justify-center min-h-[calc(100vh-8rem)]">
                    <div class="text-center max-w-2xl">
                        <h1 class="text-5xl font-bold mb-4">{{ config('app.name', 'Laravel') }}</h1>
                        <p class="text-lg text-base-content/60 mb-8">{{ __('Build something amazing.') }}</p>

                        @guest
                            <div class="flex gap-3 justify-center">
                                <x-button label="{{ __('Get Started') }}" link="{{ route('register') }}" class="btn-primary" icon="lucide.arrow-right" wire:navigate />
                                <x-button label="{{ __('Learn More') }}" link="https://laravel.com/docs" class="btn-ghost" external />
                            </div>
                        @endguest
                    </div>
                </div>
            </x-slot:content>
        </x-main>

        {{-- TOAST --}}
        <x-toast />
    </body>
</html>
