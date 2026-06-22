<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen font-sans antialiased bg-base-200/50">

        {{-- NAVBAR mobile only --}}
        <x-nav sticky class="lg:hidden">
            <x-slot:brand>
                <x-app-logo />
            </x-slot:brand>
            <x-slot:actions>
                <label for="main-drawer" class="lg:hidden mr-3">
                    <x-icon name="lucide.menu" class="cursor-pointer" />
                </label>
            </x-slot:actions>
        </x-nav>

        {{-- MAIN --}}
        <x-main full-width>
            {{-- SIDEBAR --}}
            <x-slot:sidebar drawer="main-drawer" collapsible >

                <div class="flex flex-col h-full">
                    {{-- BRAND --}}
                    <x-app-logo class="ml-5 pt-5" />

                    {{-- MENU --}}
                    <x-menu title="" activate-by-route class="flex-1">

                        <x-menu-item title="{{ __('Dashboard') }}" icon="lucide.layout-dashboard" link="{{ route('dashboard') }}" wire:navigate />
                        <x-menu-item title="{{ __('Accounts') }}" icon="lucide.wallet" link="{{ route('accounts.index') }}" wire:navigate />
                        <x-menu-item title="{{ __('Transactions') }}" icon="lucide.list" link="{{ route('transactions.index') }}" wire:navigate />
                        <x-menu-item title="{{ __('Budget') }}" icon="lucide.wallet-cards" link="{{ route('buckets.index') }}" wire:navigate />
                        <x-menu-item title="{{ __('Goals') }}" icon="lucide.target" link="{{ route('goals.index') }}" wire:navigate />
                        <x-menu-item title="{{ __('Income') }}" icon="lucide.banknote" link="{{ route('income.index') }}" wire:navigate />
                        <x-menu-item title="{{ __('Bills') }}" icon="lucide.calendar-clock" link="{{ route('bills.index') }}" wire:navigate />
                        <x-menu-item title="{{ __('Calendar') }}" icon="lucide.calendar" link="{{ route('calendar.index') }}" wire:navigate />
                        <x-menu-item title="{{ __('Imports') }}" icon="lucide.upload" link="{{ route('imports.index') }}" wire:navigate />
                        <x-menu-item title="{{ __('Categories') }}" icon="lucide.tag" link="{{ route('categories.index') }}" wire:navigate />

                    </x-menu>

                    <x-menu title="">
                        @if($user = auth()->user())

                            <div class="flex items-center justify-between pl-3 flex-nowrap overflow-hidden">
                                <x-avatar class="my-1" placeholder="{{ collect(explode(' ', $user->name))->map(fn ($part) => strtoupper(mb_substr($part, 0, 1)))->take(2)->join('') }}">
                                    <x-slot:title class="mary-hideable text-nowrap">{{ $user->name }}</x-slot:title>
                                </x-avatar>

                                <span class="mary-hideable shrink-0">
                                    <span class="flex gap-1 items-center">
                                        <form method="POST" action="{{ route('logout') }}" class="flex">
                                            @csrf
                                            <x-button icon="lucide.log-out" class="btn-circle btn-ghost btn-sm" tooltip-left="Logoff" type="submit" />
                                        </form>
                                        <x-button icon="lucide.settings" class="btn-circle btn-ghost btn-sm" tooltip-left="Settings" no-wire-navigate link="/settings"></x-button>
                                    </span>
                                </span>
                            </div>

                        @endif
                    </x-menu>
                    {{-- User --}}
                </div>
            </x-slot:sidebar>

            {{-- The $slot goes here --}}
            <x-slot:content>
                {{ $slot }}
            </x-slot:content>
        </x-main>

        {{-- TOAST --}}
        <x-toast />
    </body>
</html>
