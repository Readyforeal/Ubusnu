# FluxUI to MaryUI Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace all FluxUI components with stock MaryUI components across the entire Laravel application, then purge FluxUI completely.

**Architecture:** Install MaryUI (which brings daisyUI + Tailwind), rewrite all layouts and views to use MaryUI's component system, replace Flux PHP API calls (`Flux::toast()`) with MaryUI equivalents, then remove the FluxUI package and all its artifacts. The app layout uses MaryUI's stock sidebar-only collapsible layout. Auth pages use a simple centered form. The appearance page becomes a 12-theme daisyUI theme selector.

**Tech Stack:** Laravel 13, Livewire 4, MaryUI (robsontenorio/mary), daisyUI, Tailwind CSS v4, Alpine.js

---

## File Map

### Files to create
- None (MaryUI installer may publish `config/mary.php`)

### Files to modify
- `composer.json` — add MaryUI, remove FluxUI
- `package.json` — add daisyUI
- `resources/css/app.css` — remove Flux CSS, add daisyUI
- `resources/views/partials/head.blade.php` — remove `@fluxAppearance`
- `resources/views/layouts/app.blade.php` — rewrite as MaryUI layout wrapper
- `resources/views/layouts/app/sidebar.blade.php` — rewrite as stock MaryUI sidebar layout
- `resources/views/layouts/auth.blade.php` — rewrite as simple centered layout wrapper
- `resources/views/layouts/auth/simple.blade.php` — rewrite with MaryUI toast
- `resources/views/components/app-logo.blade.php` — remove Flux brand components
- `resources/views/components/auth-header.blade.php` — remove Flux heading/subheading
- `resources/views/components/desktop-user-menu.blade.php` — rewrite with MaryUI dropdown/menu
- `resources/views/components/passkey-registration.blade.php` — replace Flux components
- `resources/views/components/passkey-verify.blade.php` — replace Flux components
- `resources/views/dashboard.blade.php` — update layout reference
- `resources/views/partials/settings-heading.blade.php` — replace Flux heading/separator
- `resources/views/pages/settings/layout.blade.php` — replace Flux navlist
- `resources/views/pages/settings/⚡profile.blade.php` — replace Flux components + `Flux::toast()`
- `resources/views/pages/settings/⚡security.blade.php` — replace Flux components + `Flux::toast()`
- `resources/views/pages/settings/⚡appearance.blade.php` — rewrite as daisyUI theme selector
- `resources/views/pages/settings/⚡delete-user-form.blade.php` — replace Flux components
- `resources/views/pages/settings/⚡delete-user-modal.blade.php` — replace Flux modal
- `resources/views/pages/settings/⚡two-factor-setup-modal.blade.php` — replace Flux modal/otp/icons
- `resources/views/pages/settings/two-factor/⚡recovery-codes.blade.php` — replace Flux components
- `resources/views/pages/auth/login.blade.php` — replace Flux input/button/checkbox/link
- `resources/views/pages/auth/register.blade.php` — replace Flux input/button/link
- `resources/views/pages/auth/forgot-password.blade.php` — replace Flux input/button/link
- `resources/views/pages/auth/reset-password.blade.php` — replace Flux input/button
- `resources/views/pages/auth/confirm-password.blade.php` — replace Flux input/button
- `resources/views/pages/auth/two-factor-challenge.blade.php` — replace Flux otp/input/button
- `resources/views/pages/auth/verify-email.blade.php` — replace Flux text/button

### Files to delete
- `resources/views/flux/` directory (4 custom icons + 1 custom navlist group)
- `resources/views/layouts/auth/card.blade.php`
- `resources/views/layouts/auth/split.blade.php`
- `resources/views/layouts/app/header.blade.php`

---

### Task 1: Install MaryUI and daisyUI, remove FluxUI

**Files:**
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `resources/css/app.css`
- Modify: `resources/views/partials/head.blade.php`

- [ ] **Step 1: Install MaryUI via composer**

Run:
```bash
composer require robsontenorio/mary --no-interaction
```

Expected: Package installs successfully. MaryUI auto-discovers its service provider.

- [ ] **Step 2: Install daisyUI via npm**

Run:
```bash
npm install -D daisyui@latest
```

Expected: daisyUI added to `package.json` devDependencies.

- [ ] **Step 3: Update `resources/css/app.css`**

Replace the entire file with:

```css
@import 'tailwindcss';
@plugin "daisyui" {
    themes: light --default, dark, cupcake, bumblebee, emerald, corporate, synthwave, retro, cyberpunk, valentine, forest, dracula;
}

@source '../views';
@source '../../vendor/robsontenorio/mary/src/View/Components/**/*.php';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
```

- [ ] **Step 4: Remove `@fluxAppearance` from head partial**

In `resources/views/partials/head.blade.php`, replace the entire file with:

```blade
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

@vite(['resources/css/app.css', 'resources/js/app.js'])
```

Note: The `@fonts` directive loaded Instrument Sans via Bunny. daisyUI provides its own font stack. If the user wants a custom font later, they can add it back.

- [ ] **Step 5: Remove FluxUI via composer**

Run:
```bash
composer remove livewire/flux --no-interaction
```

Expected: FluxUI removed. The `use Flux\Flux` imports in Livewire components will break — we fix those in later tasks.

- [ ] **Step 6: Build frontend assets to verify CSS compiles**

Run:
```bash
npm run build
```

Expected: Vite builds successfully. If there are errors about missing Flux CSS, they should be resolved since we already removed the imports.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock package.json package-lock.json resources/css/app.css resources/views/partials/head.blade.php
git commit -m "Install MaryUI and daisyUI, remove FluxUI package"
```

---

### Task 2: Rewrite app layout with stock MaryUI sidebar

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Modify: `resources/views/layouts/app/sidebar.blade.php`
- Modify: `resources/views/components/app-logo.blade.php`
- Modify: `resources/views/components/desktop-user-menu.blade.php`
- Modify: `resources/views/dashboard.blade.php`
- Delete: `resources/views/layouts/app/header.blade.php`
- Delete: `resources/views/flux/` directory

- [ ] **Step 1: Rewrite `resources/views/layouts/app/sidebar.blade.php`**

Replace the entire file with the stock MaryUI sidebar-only collapsible layout:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
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
                    <x-icon name="o-bars-3" class="cursor-pointer" />
                </label>
            </x-slot:actions>
        </x-nav>

        {{-- MAIN --}}
        <x-main full-width>
            {{-- SIDEBAR --}}
            <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit">

                {{-- BRAND --}}
                <x-app-logo class="ml-5 pt-5" />

                {{-- MENU --}}
                <x-menu activate-by-route>

                    {{-- User --}}
                    @if($user = auth()->user())
                        <x-menu-separator />

                        <x-list-item :item="$user" value="name" sub-value="email" no-separator no-hover class="-mx-2 !-my-2 rounded">
                            <x-slot:actions>
                                <x-button icon="o-power" class="btn-circle btn-ghost btn-xs" tooltip-left="logoff" no-wire-navigate link="/logout" />
                            </x-slot:actions>
                        </x-list-item>

                        <x-menu-separator />
                    @endif

                    <x-menu-item title="{{ __('Dashboard') }}" icon="o-home" link="{{ route('dashboard') }}" wire:navigate />

                    <x-menu-sub title="{{ __('Settings') }}" icon="o-cog-6-tooth">
                        <x-menu-item title="{{ __('Profile') }}" icon="o-user" link="{{ route('profile.edit') }}" wire:navigate />
                        <x-menu-item title="{{ __('Security') }}" icon="o-shield-check" link="{{ route('security.edit') }}" wire:navigate />
                        <x-menu-item title="{{ __('Appearance') }}" icon="o-swatch" link="{{ route('appearance.edit') }}" wire:navigate />
                    </x-menu-sub>
                </x-menu>
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
```

- [ ] **Step 2: Rewrite `resources/views/layouts/app.blade.php`**

Replace the entire file with:

```blade
<x-layouts::app.sidebar :title="$title ?? null">
    {{ $slot }}
</x-layouts::app.sidebar>
```

Note: This removes the `<flux:main>` wrapper. The `<x-slot:content>` in the sidebar layout handles content wrapping.

- [ ] **Step 3: Rewrite `resources/views/components/app-logo.blade.php`**

Replace the entire file with:

```blade
@props([
    'sidebar' => false,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <x-app-logo-icon class="size-7 fill-current" />
    <span class="font-bold text-lg">{{ config('app.name', 'Laravel') }}</span>
</div>
```

- [ ] **Step 4: Rewrite `resources/views/components/desktop-user-menu.blade.php`**

This component is no longer needed — the sidebar layout shows user info via `<x-list-item>` and has a logout button directly. Replace the entire file with an empty component that renders nothing (it may still be referenced somewhere):

```blade
{{-- Desktop user menu replaced by sidebar user section --}}
```

- [ ] **Step 5: Update `resources/views/dashboard.blade.php`**

Replace the entire file with:

```blade
<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div class="relative aspect-video overflow-hidden rounded-xl border border-base-300">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-base-content/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-base-300">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-base-content/20" />
            </div>
            <div class="relative aspect-video overflow-hidden rounded-xl border border-base-300">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-base-content/20" />
            </div>
        </div>
        <div class="relative h-full flex-1 overflow-hidden rounded-xl border border-base-300">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-base-content/20" />
        </div>
    </div>
</x-layouts::app>
```

- [ ] **Step 6: Delete unused layout and Flux overrides**

Run:
```bash
rm resources/views/layouts/app/header.blade.php
rm -rf resources/views/flux/
```

- [ ] **Step 7: Build and verify the app loads**

Run:
```bash
npm run build
```

Then visit the dashboard in a browser to verify the sidebar layout renders. Check that sidebar collapses, mobile drawer works.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "Rewrite app layout with stock MaryUI sidebar"
```

---

### Task 3: Rewrite auth layouts

**Files:**
- Modify: `resources/views/layouts/auth.blade.php`
- Modify: `resources/views/layouts/auth/simple.blade.php`
- Modify: `resources/views/components/auth-header.blade.php`
- Delete: `resources/views/layouts/auth/card.blade.php`
- Delete: `resources/views/layouts/auth/split.blade.php`

- [ ] **Step 1: Rewrite `resources/views/layouts/auth/simple.blade.php`**

Replace the entire file with:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen font-sans antialiased bg-base-200">
        <div class="flex min-h-screen flex-col items-center justify-center gap-6 p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-2">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current" />
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        {{-- TOAST --}}
        <x-toast />
    </body>
</html>
```

- [ ] **Step 2: Rewrite `resources/views/layouts/auth.blade.php`**

Replace the entire file with:

```blade
<x-layouts::auth.simple :title="$title ?? null">
    {{ $slot }}
</x-layouts::auth.simple>
```

- [ ] **Step 3: Rewrite `resources/views/components/auth-header.blade.php`**

Replace the entire file with:

```blade
@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="text-xl font-bold">{{ $title }}</h1>
    <p class="text-sm text-base-content/60 mt-1">{{ $description }}</p>
</div>
```

- [ ] **Step 4: Delete unused auth layouts**

Run:
```bash
rm resources/views/layouts/auth/card.blade.php
rm resources/views/layouts/auth/split.blade.php
```

- [ ] **Step 5: Build and verify auth pages load**

Run:
```bash
npm run build
```

Visit `/login` in a browser to verify the centered auth layout renders.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "Rewrite auth layouts with MaryUI"
```

---

### Task 4: Migrate all auth page views

**Files:**
- Modify: `resources/views/pages/auth/login.blade.php`
- Modify: `resources/views/pages/auth/register.blade.php`
- Modify: `resources/views/pages/auth/forgot-password.blade.php`
- Modify: `resources/views/pages/auth/reset-password.blade.php`
- Modify: `resources/views/pages/auth/confirm-password.blade.php`
- Modify: `resources/views/pages/auth/two-factor-challenge.blade.php`
- Modify: `resources/views/pages/auth/verify-email.blade.php`
- Modify: `resources/views/components/passkey-verify.blade.php`
- Modify: `resources/views/components/passkey-registration.blade.php`

- [ ] **Step 1: Rewrite `resources/views/pages/auth/login.blade.php`**

Replace the entire file with:

```blade
<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
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

            <!-- Password -->
            <div class="relative">
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
                    <a href="{{ route('password.request') }}" class="absolute top-0 end-0 text-sm link link-primary" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
            </div>

            <!-- Remember Me -->
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
```

- [ ] **Step 2: Rewrite `resources/views/pages/auth/register.blade.php`**

Replace the entire file with:

```blade
<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Name -->
            <x-input
                name="name"
                label="{{ __('Name') }}"
                :value="old('name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                placeholder="{{ __('Full name') }}"
                inline
            />

            <!-- Email Address -->
            <x-input
                name="email"
                label="{{ __('Email address') }}"
                :value="old('email')"
                type="email"
                required
                autocomplete="email"
                placeholder="email@example.com"
                inline
            />

            <!-- Password -->
            <x-input
                name="password"
                label="{{ __('Password') }}"
                type="password"
                required
                autocomplete="new-password"
                placeholder="{{ __('Password') }}"
                inline
            />

            <!-- Confirm Password -->
            <x-input
                name="password_confirmation"
                label="{{ __('Confirm password') }}"
                type="password"
                required
                autocomplete="new-password"
                placeholder="{{ __('Confirm password') }}"
                inline
            />

            <div class="flex items-center justify-end">
                <x-button label="{{ __('Create account') }}" type="submit" class="btn-primary w-full" data-test="register-user-button" />
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-base-content/60">
            <span>{{ __('Already have an account?') }}</span>
            <a href="{{ route('login') }}" class="link link-primary" wire:navigate>{{ __('Log in') }}</a>
        </div>
    </div>
</x-layouts::auth>
```

- [ ] **Step 3: Rewrite `resources/views/pages/auth/forgot-password.blade.php`**

Replace the entire file with:

```blade
<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <x-input
                name="email"
                label="{{ __('Email address') }}"
                type="email"
                required
                autofocus
                placeholder="email@example.com"
                inline
            />

            <x-button label="{{ __('Email password reset link') }}" type="submit" class="btn-primary w-full" data-test="email-password-reset-link-button" />
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-base-content/60">
            <span>{{ __('Or, return to') }}</span>
            <a href="{{ route('login') }}" class="link link-primary" wire:navigate>{{ __('log in') }}</a>
        </div>
    </div>
</x-layouts::auth>
```

- [ ] **Step 4: Rewrite `resources/views/pages/auth/reset-password.blade.php`**

Replace the entire file with:

```blade
<x-layouts::auth :title="__('Reset password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Token -->
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <!-- Email Address -->
            <x-input
                name="email"
                value="{{ request('email') }}"
                label="{{ __('Email') }}"
                type="email"
                required
                autocomplete="email"
                inline
            />

            <!-- Password -->
            <x-input
                name="password"
                label="{{ __('Password') }}"
                type="password"
                required
                autocomplete="new-password"
                placeholder="{{ __('Password') }}"
                inline
            />

            <!-- Confirm Password -->
            <x-input
                name="password_confirmation"
                label="{{ __('Confirm password') }}"
                type="password"
                required
                autocomplete="new-password"
                placeholder="{{ __('Confirm password') }}"
                inline
            />

            <div class="flex items-center justify-end">
                <x-button label="{{ __('Reset password') }}" type="submit" class="btn-primary w-full" data-test="reset-password-button" />
            </div>
        </form>
    </div>
</x-layouts::auth>
```

- [ ] **Step 5: Rewrite `resources/views/pages/auth/confirm-password.blade.php`**

Replace the entire file with:

```blade
<x-layouts::auth :title="__('Confirm password')">
    <div class="flex flex-col gap-6">
        <x-auth-header
            :title="__('Confirm password')"
            :description="__('This is a secure area of the application. Please confirm your password before continuing.')"
        />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify
            options-route="passkey.confirm-options"
            submit-route="passkey.confirm"
            :label="__('Confirm with passkey')"
            :loading-label="__('Confirming...')"
            :separator="__('Or confirm with password')"
        />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-input
                name="password"
                label="{{ __('Password') }}"
                type="password"
                required
                autocomplete="current-password"
                placeholder="{{ __('Password') }}"
                inline
            />

            <x-button label="{{ __('Confirm') }}" type="submit" class="btn-primary w-full" data-test="confirm-password-button" />
        </form>
    </div>
</x-layouts::auth>
```

- [ ] **Step 6: Rewrite `resources/views/pages/auth/two-factor-challenge.blade.php`**

Replace the entire file with:

```blade
<x-layouts::auth :title="__('Two-factor authentication')">
    <div class="flex flex-col gap-6">
        <div
            class="relative w-full h-auto"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                init() {
                    if (! this.showRecoveryInput) {
                        this.$nextTick(() => this.$refs.codeInput?.focus());
                    }
                },
                toggleInput() {
                    this.showRecoveryInput = !this.showRecoveryInput;
                    this.code = '';
                    this.recovery_code = '';

                    $nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : this.$refs.codeInput?.focus();
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    :title="__('Authentication code')"
                    :description="__('Enter the authentication code provided by your authenticator application.')"
                />
            </div>

            <div x-show="showRecoveryInput">
                <x-auth-header
                    :title="__('Recovery code')"
                    :description="__('Please confirm access to your account by entering one of your emergency recovery codes.')"
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf

                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <div class="my-5">
                            <x-input
                                name="code"
                                x-model="code"
                                x-ref="codeInput"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]*"
                                maxlength="6"
                                placeholder="{{ __('6-digit code') }}"
                                autocomplete="one-time-code"
                                required
                            />
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="my-5">
                            <x-input
                                name="recovery_code"
                                x-ref="recovery_code"
                                x-model="recovery_code"
                                x-bind:required="showRecoveryInput"
                                type="text"
                                autocomplete="one-time-code"
                                placeholder="{{ __('Recovery code') }}"
                            />
                        </div>

                        @error('recovery_code')
                            <p class="text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-button
                        label="{{ __('Continue') }}"
                        type="submit"
                        class="btn-primary w-full"
                    />
                </div>

                <div class="mt-5 space-x-0.5 text-sm leading-5 text-center">
                    <span class="opacity-50">{{ __('or you can') }}</span>
                    <div class="inline font-medium underline cursor-pointer opacity-80">
                        <span x-show="!showRecoveryInput" @click="toggleInput()">{{ __('login using a recovery code') }}</span>
                        <span x-show="showRecoveryInput" @click="toggleInput()">{{ __('login using an authentication code') }}</span>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-layouts::auth>
```

- [ ] **Step 7: Rewrite `resources/views/pages/auth/verify-email.blade.php`**

Replace the entire file with:

```blade
<x-layouts::auth :title="__('Email verification')">
    <div class="mt-4 flex flex-col gap-6">
        <p class="text-center text-sm">
            {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
        </p>

        @if (session('status') == 'verification-link-sent')
            <p class="text-center text-sm font-medium text-success">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </p>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-button label="{{ __('Resend verification email') }}" type="submit" class="btn-primary w-full" />
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-button label="{{ __('Log out') }}" type="submit" class="btn-ghost text-sm" data-test="logout-button" />
            </form>
        </div>
    </div>
</x-layouts::auth>
```

- [ ] **Step 8: Rewrite `resources/views/components/passkey-verify.blade.php`**

Replace the entire file with:

```blade
@props([
    'optionsRoute' => 'passkey.login-options',
    'submitRoute' => 'passkey.login',
    'label' => __('Sign in with a passkey'),
    'loadingLabel' => __('Authenticating...'),
    'separator' => __('Or continue with email'),
])

@assets
@vite('resources/js/passkeys.js')
@endassets

<div
    x-data="{
        supported: false,
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        init() {
            this.updateSupport();
            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async verify() {
            this.loading = true;
            this.error = null;
            try {
                const response = await window.Passkeys.verify({
                    routes: {
                        options: '{{ route($optionsRoute) }}',
                        submit: '{{ route($submitRoute) }}',
                    },
                });
                Livewire.navigate(response.redirect || '/dashboard');
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = e.message;
                }
            } finally {
                this.loading = false;
            }
        },
    }"
>
    <template x-if="supported">
        <div>
            <div class="grid gap-2">
                <x-button
                    label="{{ $label }}"
                    icon="o-finger-print"
                    class="btn-outline w-full"
                    x-on:click="verify()"
                    x-bind:disabled="loading"
                />
                <p x-show="error" x-text="error" x-cloak
                   class="text-sm text-center text-error"></p>
            </div>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-base-300"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="px-2 text-base-content/50 bg-base-200">
                        {{ $separator }}
                    </span>
                </div>
            </div>
        </div>
    </template>
</div>
```

- [ ] **Step 9: Rewrite `resources/views/components/passkey-registration.blade.php`**

Replace the entire file with:

```blade
@assets
@vite('resources/js/passkeys.js')
@endassets

<div
    x-data="{
        supported: false,
        showForm: false,
        name: '',
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        init() {
            this.updateSupport();
            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async register() {
            if (!this.name.trim()) return;

            this.loading = true;
            this.error = null;

            try {
                await window.Passkeys.register({ name: this.name });
                this.name = '';
                this.showForm = false;
                await $wire.loadPasskeys();
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = e.message;
                }
            } finally {
                this.loading = false;
            }
        },
        cancel() {
            this.showForm = false;
            this.name = '';
            this.error = null;
        },
    }"
>
    <template x-if="!supported">
        <p class="text-sm text-base-content/60">{{ __('Passkeys are not supported in this browser.') }}</p>
    </template>

    <template x-if="supported && !showForm">
        <div>
            <x-button
                label="{{ __('Add passkey') }}"
                icon="o-plus"
                class="btn-primary"
                x-on:click="showForm = true"
            />
        </div>
    </template>

    <template x-if="supported && showForm">
        <div class="space-y-4 rounded-lg border border-base-300 bg-base-200/50 p-4">
            <x-input
                label="{{ __('Passkey name') }}"
                x-model="name"
                placeholder="{{ __('e.g., MacBook Pro, iPhone') }}"
                x-on:keydown.enter.prevent="register()"
                x-ref="passkeyNameInput"
                x-init="$nextTick(() => $refs.passkeyNameInput?.focus())"
            />
            <p class="text-sm text-base-content/60">{{ __('Give this passkey a name to help you identify it later.') }}</p>

            <p x-show="error" x-text="error" x-cloak class="text-sm text-error"></p>

            <div class="flex gap-2">
                <x-button
                    label="{{ __('Register passkey') }}"
                    class="btn-primary"
                    x-on:click="register()"
                    x-bind:disabled="loading || !name.trim()"
                />
                <x-button
                    label="{{ __('Cancel') }}"
                    class="btn-ghost"
                    x-on:click="cancel()"
                />
            </div>
        </div>
    </template>
</div>
```

- [ ] **Step 10: Build and verify auth pages**

Run:
```bash
npm run build
```

Visit `/login`, `/register`, `/forgot-password` in a browser. Verify forms render and submit correctly.

- [ ] **Step 11: Commit**

```bash
git add -A
git commit -m "Migrate all auth page views to MaryUI"
```

---

### Task 5: Migrate settings pages — profile and delete user

**Files:**
- Modify: `resources/views/partials/settings-heading.blade.php`
- Modify: `resources/views/pages/settings/layout.blade.php`
- Modify: `resources/views/pages/settings/⚡profile.blade.php`
- Modify: `resources/views/pages/settings/⚡delete-user-form.blade.php`
- Modify: `resources/views/pages/settings/⚡delete-user-modal.blade.php`

- [ ] **Step 1: Rewrite `resources/views/partials/settings-heading.blade.php`**

Replace the entire file with:

```blade
<div class="relative mb-6 w-full">
    <x-header title="{{ __('Settings') }}" subtitle="{{ __('Manage your profile and account settings') }}" separator />
</div>
```

- [ ] **Step 2: Rewrite `resources/views/pages/settings/layout.blade.php`**

Replace the entire file with:

```blade
<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <x-menu activate-by-route>
            <x-menu-item title="{{ __('Profile') }}" link="{{ route('profile.edit') }}" wire:navigate />
            <x-menu-item title="{{ __('Security') }}" link="{{ route('security.edit') }}" wire:navigate />
            <x-menu-item title="{{ __('Appearance') }}" link="{{ route('appearance.edit') }}" wire:navigate />
        </x-menu>
    </div>

    <hr class="md:hidden my-4" />

    <div class="flex-1 self-stretch max-md:pt-6">
        @if(isset($heading) && $heading)
            <h2 class="text-lg font-semibold">{{ $heading }}</h2>
        @endif
        @if(isset($subheading) && $subheading)
            <p class="text-sm text-base-content/60 mt-1">{{ $subheading }}</p>
        @endif

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
```

- [ ] **Step 3: Rewrite `resources/views/pages/settings/⚡profile.blade.php`**

Replace the entire file with:

```blade
<?php

use App\Concerns\ProfileValidationRules;
/* @chisel-email-verification */
use Illuminate\Contracts\Auth\MustVerifyEmail;
/* @end-chisel-email-verification */
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;
    use Toast;

    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->success(__('Profile updated.'));
    }

    /* @chisel-email-verification */
    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }
    /* @end-chisel-email-verification */
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <x-input wire:model="name" label="{{ __('Name') }}" type="text" required autofocus autocomplete="name" inline />

            <div>
                <x-input wire:model="email" label="{{ __('Email') }}" type="email" required autocomplete="email" inline />

                {{-- @chisel-email-verification --}}
                @if ($this->hasUnverifiedEmail)
                    <div>
                        <p class="text-sm mt-4">
                            {{ __('Your email address is unverified.') }}

                            <a href="#" class="link link-primary text-sm" wire:click.prevent="resendVerificationNotification">
                                {{ __('Click here to re-send the verification email.') }}
                            </a>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-success">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
                {{-- @end-chisel-email-verification --}}
            </div>

            <div class="flex items-center gap-4">
                <div class="flex items-center justify-end">
                    <x-button label="{{ __('Save') }}" type="submit" class="btn-primary w-full" data-test="update-profile-button" />
                </div>
            </div>
        </form>

        {{-- @chisel-email-verification --}}
        @if ($this->showDeleteUser)
        {{-- @end-chisel-email-verification --}}
            <livewire:pages::settings.delete-user-form />
        {{-- @chisel-email-verification --}}
        @endif
        {{-- @end-chisel-email-verification --}}
    </x-pages::settings.layout>
</section>
```

- [ ] **Step 4: Rewrite `resources/views/pages/settings/⚡delete-user-form.blade.php`**

Replace the entire file with:

```blade
<?php

use Livewire\Component;

new class extends Component {}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <h3 class="text-lg font-semibold">{{ __('Delete account') }}</h3>
        <p class="text-sm text-base-content/60 mt-1">{{ __('Delete your account and all of its resources') }}</p>
    </div>

    <x-button label="{{ __('Delete account') }}" class="btn-error" @click="$dispatch('open-delete-user-modal')" data-test="delete-user-button" />

    <livewire:pages::settings.delete-user-modal />
</section>
```

- [ ] **Step 5: Rewrite `resources/views/pages/settings/⚡delete-user-modal.blade.php`**

Replace the entire file with:

```blade
<?php

use App\Concerns\PasswordValidationRules;
use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

new class extends Component {
    use PasswordValidationRules;

    public string $password = '';
    public bool $showModal = false;

    protected $listeners = ['open-delete-user-modal' => 'openModal'];

    public function openModal(): void
    {
        $this->showModal = true;
    }

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <x-modal wire:model="showModal" title="{{ __('Are you sure you want to delete your account?') }}">
        <p class="text-sm text-base-content/60">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
        </p>

        <form method="POST" wire:submit="deleteUser" class="mt-4 space-y-6">
            <x-input wire:model="password" label="{{ __('Password') }}" type="password" inline />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <x-button label="{{ __('Cancel') }}" @click="$wire.showModal = false" />
                <x-button label="{{ __('Delete account') }}" type="submit" class="btn-error" data-test="confirm-delete-user-button" />
            </div>
        </form>
    </x-modal>
</div>
```

- [ ] **Step 6: Build and verify settings profile page**

Run:
```bash
npm run build
```

Visit the settings profile page in a browser. Verify the form renders, saves, and the delete account modal opens.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "Migrate settings profile and delete user pages to MaryUI"
```

---

### Task 6: Migrate settings pages — security (password, 2FA, passkeys)

**Files:**
- Modify: `resources/views/pages/settings/⚡security.blade.php`
- Modify: `resources/views/pages/settings/⚡two-factor-setup-modal.blade.php`
- Modify: `resources/views/pages/settings/two-factor/⚡recovery-codes.blade.php`

- [ ] **Step 1: Rewrite `resources/views/pages/settings/⚡security.blade.php`**

Replace the entire file with:

```blade
<?php

use App\Concerns\PasswordValidationRules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;
/* @chisel-passkeys */
use Laravel\Passkeys\Actions\DeletePasskey;
use Livewire\Attributes\Locked;
/* @end-chisel-passkeys */
/* @chisel-2fa */
use Livewire\Attributes\On;
/* @end-chisel-2fa */

new #[Title('Security settings')] class extends Component {
    use PasswordValidationRules;
    use Toast;

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /* @chisel-2fa */
    public bool $canManageTwoFactor;

    public bool $twoFactorEnabled;

    public bool $requiresConfirmation;
    /* @end-chisel-2fa */

    /* @chisel-passkeys */
    #[Locked]
    public bool $canManagePasskeys;

    #[Locked]
    public array $passkeys = [];

    public bool $showDeleteModal = false;

    #[Locked]
    public ?int $deletingPasskeyId = null;

    #[Locked]
    public string $deletingPasskeyName = '';
    /* @end-chisel-passkeys */

    /**
     * Mount the component.
     */
    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        /* @chisel-2fa */
        $this->canManageTwoFactor = Features::canManageTwoFactorAuthentication();

        if ($this->canManageTwoFactor) {
            if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
                $disableTwoFactorAuthentication(auth()->user());
            }

            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
            $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
        }
        /* @end-chisel-2fa */

        /* @chisel-passkeys */
        $this->canManagePasskeys = Features::canManagePasskeys();

        if ($this->canManagePasskeys) {
            $this->loadPasskeys();
        }
        /* @end-chisel-passkeys */
    }

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => $this->currentPasswordRules(),
                'password' => $this->passwordRules(),
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => $validated['password'],
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->success(__('Password updated.'));
    }

    /* @chisel-passkeys */
    /**
     * Load the user's passkeys.
     */
    public function loadPasskeys(): void
    {
        $this->passkeys = auth()->user()->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get()
            ->map(fn ($passkey) => [
                'id' => $passkey->id,
                'name' => $passkey->name,
                'authenticator' => $passkey->authenticator,
                'created_at_diff' => $passkey->created_at->diffForHumans(),
                'last_used_at_diff' => $passkey->last_used_at?->diffForHumans(),
            ])
            ->toArray();
    }

    /**
     * Show the delete confirmation modal.
     */
    public function confirmDelete(int $passkeyId): void
    {
        $passkey = auth()->user()->passkeys()->findOrFail($passkeyId);

        $this->deletingPasskeyId = $passkey->id;
        $this->deletingPasskeyName = $passkey->name;
        $this->showDeleteModal = true;
    }

    /**
     * Delete the passkey.
     */
    public function deletePasskey(DeletePasskey $deletePasskey): void
    {
        if (! $this->deletingPasskeyId) {
            return;
        }

        $passkey = auth()->user()->passkeys()->findOrFail($this->deletingPasskeyId);

        $deletePasskey(auth()->user(), $passkey);

        $this->closeDeleteModal();
        $this->loadPasskeys();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteModal(): void
    {
        $this->showDeleteModal = false;
        $this->deletingPasskeyId = null;
        $this->deletingPasskeyName = '';
    }
    /* @end-chisel-passkeys */

    /* @chisel-2fa */
    /**
     * Handle the two-factor authentication enabled event.
     */
    #[On('two-factor-enabled')]
    public function onTwoFactorEnabled(): void
    {
        $this->twoFactorEnabled = true;
    }

    /**
     * Disable two-factor authentication for the user.
     */
    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }
    /* @end-chisel-2fa */
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <x-input
                wire:model="current_password"
                label="{{ __('Current password') }}"
                type="password"
                required
                autocomplete="current-password"
                inline
            />
            <x-input
                wire:model="password"
                label="{{ __('New password') }}"
                type="password"
                required
                autocomplete="new-password"
                inline
            />
            <x-input
                wire:model="password_confirmation"
                label="{{ __('Confirm password') }}"
                type="password"
                required
                autocomplete="new-password"
                inline
            />

            <div class="flex items-center gap-4">
                <x-button label="{{ __('Save') }}" type="submit" class="btn-primary" data-test="update-password-button" />
            </div>
        </form>

        {{-- @chisel-2fa --}}
        @if ($canManageTwoFactor)
            <section class="mt-12">
                <h3 class="text-lg font-semibold">{{ __('Two-factor authentication') }}</h3>
                <p class="text-sm text-base-content/60 mt-1">{{ __('Manage your two-factor authentication settings') }}</p>

                <div class="flex flex-col w-full mx-auto space-y-6 text-sm mt-4" wire:cloak>
                    @if ($twoFactorEnabled)
                        <div class="space-y-4">
                            <p class="text-sm">
                                {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                            </p>

                            <div class="flex justify-start">
                                <x-button
                                    label="{{ __('Disable 2FA') }}"
                                    class="btn-error"
                                    wire:click="disable"
                                />
                            </div>

                            <livewire:pages::settings.two-factor.recovery-codes :$requiresConfirmation />
                        </div>
                    @else
                        <div class="space-y-4">
                            <p class="text-sm text-base-content/60">
                                {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                            </p>

                            <x-button
                                label="{{ __('Enable 2FA') }}"
                                class="btn-primary"
                                wire:click="$dispatch('start-two-factor-setup')"
                            />

                            <livewire:pages::settings.two-factor-setup-modal :requires-confirmation="$requiresConfirmation" />
                        </div>
                    @endif
                </div>
            </section>
        @endif
        {{-- @end-chisel-2fa --}}

        {{-- @chisel-passkeys --}}
        @if ($canManagePasskeys)
            <section class="mt-12">
                <h3 class="text-lg font-semibold">{{ __('Passkeys') }}</h3>
                <p class="text-sm text-base-content/60 mt-1">{{ __('Manage your passkeys for passwordless sign-in') }}</p>

                <div class="mt-6 flex flex-col w-full mx-auto space-y-6 text-sm" wire:cloak>
                    <div class="border rounded-lg border-base-300 overflow-hidden">
                        @forelse ($passkeys as $passkey)
                            <div class="flex items-center justify-between p-4 {{ ! $loop->last ? 'border-b border-base-300' : '' }}">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-base-200">
                                        <x-icon name="o-key" class="size-5 text-base-content/50" />
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2.5">
                                            <p class="font-medium tracking-tight">{{ $passkey['name'] }}</p>
                                            @if ($passkey['authenticator'])
                                                <x-badge value="{{ $passkey['authenticator'] }}" class="badge-sm" />
                                            @endif
                                        </div>
                                        <p class="text-base-content/50 text-xs">
                                            {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                            @if ($passkey['last_used_at_diff'])
                                                <span class="opacity-50 mx-1">/</span>
                                                {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                            @endif
                                        </p>
                                    </div>
                                </div>

                                <x-button
                                    icon="o-trash"
                                    class="btn-ghost btn-sm text-error"
                                    wire:click="confirmDelete({{ $passkey['id'] }})"
                                />
                            </div>
                        @empty
                            <div class="p-8 text-center">
                                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-base-200">
                                    <x-icon name="o-key" class="size-7 text-base-content/40" />
                                </div>
                                <p class="font-medium">{{ __('No passkeys yet') }}</p>
                                <p class="mt-1 text-sm text-base-content/60">{{ __('Add a passkey to sign in without a password') }}</p>
                            </div>
                        @endforelse
                    </div>

                    <x-passkey-registration />
                </div>
            </section>
        @endif
        {{-- @end-chisel-passkeys --}}
    </x-pages::settings.layout>

    {{-- @chisel-passkeys --}}
    <x-modal wire:model="showDeleteModal" title="{{ __('Remove passkey') }}">
        <p class="text-sm text-base-content/60">
            {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
        </p>

        <div class="flex gap-3 justify-end mt-4">
            <x-button
                label="{{ __('Cancel') }}"
                wire:click="closeDeleteModal"
            />
            <x-button
                label="{{ __('Remove passkey') }}"
                class="btn-error"
                wire:click="deletePasskey"
            />
        </div>
    </x-modal>
    {{-- @end-chisel-passkeys --}}
</section>
```

- [ ] **Step 2: Rewrite `resources/views/pages/settings/⚡two-factor-setup-modal.blade.php`**

Replace the entire file with:

```blade
<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public bool $requiresConfirmation;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showVerificationStep = false;

    public bool $setupComplete = false;

    public bool $showModal = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    /**
     * Mount the component.
     */
    public function mount(bool $requiresConfirmation): void
    {
        $this->requiresConfirmation = $requiresConfirmation;
    }

    #[On('start-two-factor-setup')]
    public function startTwoFactorSetup(): void
    {
        $this->showModal = true;

        $enableTwoFactorAuthentication = app(EnableTwoFactorAuthentication::class);
        $enableTwoFactorAuthentication(auth()->user());

        $this->loadSetupData();
    }

    /**
     * Load the two-factor authentication setup data for the user.
     */
    private function loadSetupData(): void
    {
        $user = auth()->user()?->fresh();

        try {
            if (! $user || ! $user->two_factor_secret) {
                throw new Exception('Two-factor setup secret is not available.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    /**
     * Show the two-factor verification step if necessary.
     */
    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
        $this->dispatch('two-factor-enabled');
    }

    /**
     * Confirm two-factor authentication for the user.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->setupComplete = true;

        $this->closeModal();

        $this->dispatch('two-factor-enabled');
    }

    /**
     * Reset two-factor verification state.
     */
    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    /**
     * Close the two-factor authentication modal.
     */
    public function closeModal(): void
    {
        $this->showModal = false;

        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showVerificationStep',
            'setupComplete',
        );

        $this->resetErrorBag();
    }

    /**
     * Get the current modal configuration state.
     */
    #[Computed]
    public function modalConfig(): array
    {
        if ($this->setupComplete) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.'),
            'buttonText' => __('Continue'),
        ];
    }
}; ?>

<x-modal wire:model="showModal" :title="$this->modalConfig['title']">
    <div class="space-y-6">
        <p class="text-sm text-base-content/60">{{ $this->modalConfig['description'] }}</p>

        @if ($showVerificationStep)
            <div class="space-y-6">
                <div
                    class="flex flex-col items-center space-y-3 justify-center"
                    x-data
                    x-init="$nextTick(() => $el.querySelector('input')?.focus())"
                >
                    <x-input
                        name="code"
                        wire:model="code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        placeholder="{{ __('6-digit code') }}"
                        autocomplete="one-time-code"
                        class="text-center"
                    />
                </div>

                <div class="flex items-center space-x-3">
                    <x-button
                        label="{{ __('Back') }}"
                        class="flex-1"
                        wire:click="resetVerification"
                    />

                    <x-button
                        label="{{ __('Confirm') }}"
                        class="btn-primary flex-1"
                        wire:click="confirmTwoFactor"
                        x-bind:disabled="$wire.code.length < 6"
                    />
                </div>
            </div>
        @else
            @error('setupData')
                <x-alert title="{{ $message }}" icon="o-x-circle" class="alert-error" />
            @enderror

            <div class="flex justify-center">
                <div class="relative w-64 overflow-hidden border rounded-lg border-base-300 aspect-square">
                    @empty($qrCodeSvg)
                        <div class="absolute inset-0 flex items-center justify-center bg-base-200 animate-pulse">
                            <x-loading class="loading-spinner" />
                        </div>
                    @else
                        <div class="flex items-center justify-center h-full p-4 bg-white">
                            <div class="bg-white p-3 rounded">
                                {!! $qrCodeSvg !!}
                            </div>
                        </div>
                    @endempty
                </div>
            </div>

            <div>
                <x-button
                    label="{{ $this->modalConfig['buttonText'] }}"
                    :disabled="$errors->has('setupData')"
                    class="btn-primary w-full"
                    wire:click="showVerificationIfNecessary"
                />
            </div>

            <div class="space-y-4">
                <div class="relative flex items-center justify-center w-full">
                    <div class="absolute inset-0 w-full h-px top-1/2 bg-base-300"></div>
                    <span class="relative px-2 text-sm bg-base-100 text-base-content/50">
                        {{ __('or, enter the code manually') }}
                    </span>
                </div>

                <div
                    class="flex items-center space-x-2"
                    x-data="{
                        copied: false,
                        async copy() {
                            try {
                                await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                this.copied = true;
                                setTimeout(() => this.copied = false, 1500);
                            } catch (e) {
                                console.warn('Could not copy to clipboard');
                            }
                        }
                    }"
                >
                    <div class="flex items-stretch w-full border rounded-xl border-base-300">
                        @empty($manualSetupKey)
                            <div class="flex items-center justify-center w-full p-3 bg-base-200">
                                <x-loading class="loading-spinner loading-sm" />
                            </div>
                        @else
                            <input
                                type="text"
                                readonly
                                value="{{ $manualSetupKey }}"
                                class="w-full p-3 bg-transparent outline-none"
                            />

                            <button
                                @click="copy()"
                                class="px-3 transition-colors border-l cursor-pointer border-base-300 hover:bg-base-200"
                            >
                                <x-icon x-show="!copied" name="o-document-duplicate" class="size-5" />
                                <x-icon x-show="copied" name="o-check" class="size-5 text-success" />
                            </button>
                        @endempty
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-modal>
```

- [ ] **Step 3: Rewrite `resources/views/pages/settings/two-factor/⚡recovery-codes.blade.php`**

Replace the entire file with:

```blade
<?php

use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public array $recoveryCodes = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->loadRecoveryCodes();
    }

    /**
     * Generate new recovery codes for the user.
     */
    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generateNewRecoveryCodes): void
    {
        $generateNewRecoveryCodes(auth()->user());

        $this->loadRecoveryCodes();
    }

    /**
     * Load the recovery codes for the user.
     */
    private function loadRecoveryCodes(): void
    {
        $user = auth()->user();

        if ($user->hasEnabledTwoFactorAuthentication() && $user->two_factor_recovery_codes) {
            try {
                $this->recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
            } catch (Exception) {
                $this->addError('recoveryCodes', 'Failed to load recovery codes');

                $this->recoveryCodes = [];
            }
        }
    }
}; ?>

<div
    class="py-6 space-y-6 border shadow-sm rounded-xl border-base-300"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="px-6 space-y-2">
        <div class="flex items-center gap-2">
            <x-icon name="o-lock-closed" class="size-4" />
            <h3 class="text-lg font-semibold">{{ __('2FA recovery codes') }}</h3>
        </div>
        <p class="text-sm text-base-content/60">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </p>
    </div>

    <div class="px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-button
                x-show="!showRecoveryCodes"
                label="{{ __('View recovery codes') }}"
                icon="o-eye"
                class="btn-primary"
                @click="showRecoveryCodes = true;"
                aria-expanded="false"
                aria-controls="recovery-codes-section"
            />

            <x-button
                x-show="showRecoveryCodes"
                label="{{ __('Hide recovery codes') }}"
                icon="o-eye-slash"
                class="btn-primary"
                @click="showRecoveryCodes = false"
                aria-expanded="true"
                aria-controls="recovery-codes-section"
            />

            @if (filled($recoveryCodes))
                <x-button
                    x-show="showRecoveryCodes"
                    label="{{ __('Regenerate codes') }}"
                    icon="o-arrow-path"
                    wire:click="regenerateRecoveryCodes"
                />
            @endif
        </div>

        <div
            x-show="showRecoveryCodes"
            x-transition
            id="recovery-codes-section"
            class="relative overflow-hidden"
            x-bind:aria-hidden="!showRecoveryCodes"
        >
            <div class="mt-3 space-y-3">
                @error('recoveryCodes')
                    <x-alert title="{{ $message }}" icon="o-x-circle" class="alert-error" />
                @enderror

                @if (filled($recoveryCodes))
                    <div
                        class="grid gap-1 p-4 font-mono text-sm rounded-lg bg-base-200"
                        role="list"
                        aria-label="{{ __('Recovery codes') }}"
                    >
                        @foreach($recoveryCodes as $code)
                            <div
                                role="listitem"
                                class="select-text"
                                wire:loading.class="opacity-50 animate-pulse"
                            >
                                {{ $code }}
                            </div>
                        @endforeach
                    </div>
                    <p class="text-xs text-base-content/60">
                        {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 4: Build and verify security settings page**

Run:
```bash
npm run build
```

Visit the security settings page. Verify password update form, 2FA setup modal, and passkeys section all render.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "Migrate security settings page to MaryUI"
```

---

### Task 7: Migrate appearance settings page with daisyUI theme selector

**Files:**
- Modify: `resources/views/pages/settings/⚡appearance.blade.php`

- [ ] **Step 1: Rewrite `resources/views/pages/settings/⚡appearance.blade.php`**

Replace the entire file with:

```blade
<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div
            x-data="{
                theme: localStorage.getItem('theme') || 'light',
                themes: [
                    { name: 'light', label: 'Light' },
                    { name: 'dark', label: 'Dark' },
                    { name: 'cupcake', label: 'Cupcake' },
                    { name: 'bumblebee', label: 'Bumblebee' },
                    { name: 'emerald', label: 'Emerald' },
                    { name: 'corporate', label: 'Corporate' },
                    { name: 'synthwave', label: 'Synthwave' },
                    { name: 'retro', label: 'Retro' },
                    { name: 'cyberpunk', label: 'Cyberpunk' },
                    { name: 'valentine', label: 'Valentine' },
                    { name: 'forest', label: 'Forest' },
                    { name: 'dracula', label: 'Dracula' },
                ],
                setTheme(name) {
                    this.theme = name;
                    localStorage.setItem('theme', name);
                    document.documentElement.setAttribute('data-theme', name);
                },
                init() {
                    document.documentElement.setAttribute('data-theme', this.theme);
                }
            }"
        >
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <template x-for="t in themes" :key="t.name">
                    <button
                        @click="setTheme(t.name)"
                        :class="theme === t.name ? 'ring-2 ring-primary ring-offset-2 ring-offset-base-100' : ''"
                        class="rounded-lg border border-base-300 overflow-hidden cursor-pointer transition-all hover:scale-105"
                        :data-theme="t.name"
                    >
                        <div class="bg-base-100 p-3">
                            <div class="flex gap-1 mb-2">
                                <div class="rounded-full size-3 bg-primary"></div>
                                <div class="rounded-full size-3 bg-secondary"></div>
                                <div class="rounded-full size-3 bg-accent"></div>
                            </div>
                            <div class="space-y-1">
                                <div class="rounded h-2 w-full bg-base-content/20"></div>
                                <div class="rounded h-2 w-3/4 bg-base-content/20"></div>
                            </div>
                        </div>
                        <div class="bg-base-200 px-3 py-2 text-xs font-medium text-base-content text-center" x-text="t.label"></div>
                    </button>
                </template>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
```

- [ ] **Step 2: Add theme persistence script to the head partial**

In `resources/views/partials/head.blade.php`, add a small inline script before `@vite` to prevent theme flash on page load. Replace the file with:

```blade
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<script>document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'light');</script>

@vite(['resources/css/app.css', 'resources/js/app.js'])
```

- [ ] **Step 3: Update layouts to use dynamic theme from localStorage**

In `resources/views/layouts/app/sidebar.blade.php`, change the opening `<html>` tag from:
```html
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">
```
to:
```html
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
```

In `resources/views/layouts/auth/simple.blade.php`, make the same change — remove the `data-theme="light"` attribute since the inline script in `<head>` handles it.

- [ ] **Step 4: Build and verify appearance settings**

Run:
```bash
npm run build
```

Visit the appearance settings page. Click different themes and verify they apply. Refresh the page and verify the theme persists.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "Add daisyUI theme selector on appearance settings page"
```

---

### Task 8: Final cleanup and purge all Flux remnants

**Files:**
- Various — final grep and cleanup

- [ ] **Step 1: Search for any remaining Flux references**

Run:
```bash
grep -ri "flux" resources/views/ --include="*.blade.php" -l
grep -ri "flux" app/ --include="*.php" -l
grep -ri "@fluxScripts\|@fluxAppearance" resources/ -l
grep -ri "livewire/flux" composer.json composer.lock
```

Expected: No results. If any files still reference Flux, fix them.

- [ ] **Step 2: Search for any remaining `use Flux\Flux` imports**

Run:
```bash
grep -r "use Flux" app/ resources/ --include="*.php" --include="*.blade.php"
```

Expected: No results. These were replaced with `use Mary\Traits\Toast` in Tasks 5 and 6.

- [ ] **Step 3: Verify the flux views directory is deleted**

Run:
```bash
ls resources/views/flux/ 2>&1
```

Expected: "No such file or directory"

- [ ] **Step 4: Verify deleted layout files are gone**

Run:
```bash
ls resources/views/layouts/auth/card.blade.php 2>&1
ls resources/views/layouts/auth/split.blade.php 2>&1
ls resources/views/layouts/app/header.blade.php 2>&1
```

Expected: "No such file or directory" for all three

- [ ] **Step 5: Run Laravel Pint to fix formatting**

Run:
```bash
vendor/bin/pint --dirty --format agent
```

Expected: Clean or auto-fixed formatting.

- [ ] **Step 6: Run the test suite**

Run:
```bash
php artisan test --compact
```

Expected: All tests pass. If any tests reference Flux components, update them to use MaryUI equivalents.

- [ ] **Step 7: Build and smoke test the full application**

Run:
```bash
npm run build
```

Then manually verify in a browser:
- Dashboard loads with sidebar layout
- Sidebar collapses/expands on desktop
- Mobile drawer works
- Login, register, forgot password pages render
- Settings pages all render (profile, security, appearance)
- Theme switching works and persists
- Logout works from sidebar

- [ ] **Step 8: Final commit**

```bash
git add -A
git commit -m "Final cleanup: purge all FluxUI remnants"
```
