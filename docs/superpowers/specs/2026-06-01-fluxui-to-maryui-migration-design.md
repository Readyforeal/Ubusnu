# FluxUI to MaryUI Migration

## Overview

Replace all FluxUI components with MaryUI in this Laravel 13 / Livewire 4 application. Every existing page (auth, dashboard, settings) is preserved but rebuilt using stock MaryUI components. No custom components — only what MaryUI provides out of the box.

## Decisions

- **Layout:** Stock MaryUI sidebar-only layout with collapsible toggle (`<x-main>` + `<x-slot:sidebar drawer="main-drawer" collapsible>`)
- **Auth pages:** Simple centered form on plain background — no card or split variants
- **Theme:** daisyUI theme selector on the appearance settings page with 12 themes
- **OTP input:** Standard `<x-input>` (no dedicated OTP component)
- **Custom code:** None. Stock MaryUI only. User will customize later if needed.

## Scope

### Pages to migrate

| Page | Current file | Key Flux components used |
|------|-------------|------------------------|
| Dashboard | `pages/dashboard.blade.php` | Layout only |
| Login | `pages/auth/login.blade.php` | input, checkbox, button, link |
| Register | `pages/auth/register.blade.php` | input, button, link |
| Forgot password | `pages/auth/forgot-password.blade.php` | input, button, link |
| Reset password | `pages/auth/reset-password.blade.php` | input, button |
| Confirm password | `pages/auth/confirm-password.blade.php` | input, button |
| 2FA challenge | `pages/auth/two-factor-challenge.blade.php` | otp, input, button, text |
| Verify email | `pages/auth/verify-email.blade.php` | text, button |
| Settings layout | `pages/settings/layout.blade.php` | navlist, heading, subheading, separator |
| Profile | `pages/settings/profile.blade.php` | input, text, link, button |
| Security | `pages/settings/security.blade.php` | input, button, heading, subheading, text, modal, badge, icon |
| Appearance | `pages/settings/appearance.blade.php` | radio.group, radio |
| Delete user form | `pages/settings/delete-user-form.blade.php` | heading, subheading, button, modal.trigger |
| Delete user modal | `pages/settings/delete-user-modal.blade.php` | modal, heading, subheading, input, button |
| 2FA setup modal | `pages/settings/two-factor-setup-modal.blade.php` | modal, heading, text, otp, button, callout, icon |
| Recovery codes | `pages/settings/two-factor/recovery-codes.blade.php` | icon, heading, text, button, callout |

### Components to migrate

| Component | Current file |
|-----------|-------------|
| App logo | `components/app-logo.blade.php` |
| App logo icon | `components/app-logo-icon.blade.php` |
| Auth header | `components/auth-header.blade.php` |
| Auth session status | `components/auth-session-status.blade.php` |
| Desktop user menu | `components/desktop-user-menu.blade.php` |
| Passkey registration | `components/passkey-registration.blade.php` |
| Passkey verify | `components/passkey-verify.blade.php` |
| Placeholder pattern | `components/placeholder-pattern.blade.php` |
| Settings heading (partial) | `partials/settings-heading.blade.php` |
| Head partial | `partials/head.blade.php` |

### Other views

| View | File | Notes |
|------|------|-------|
| Dashboard | `dashboard.blade.php` | Main dashboard page |
| Welcome | `welcome.blade.php` | Landing page — check for Flux usage |
| App layout wrapper | `layouts/app.blade.php` | Top-level layout selector |
| Auth layout wrapper | `layouts/auth.blade.php` | Top-level auth layout selector |

### Layouts to replace

| Layout | Current file | MaryUI replacement |
|--------|-------------|-------------------|
| App header | `layouts/app/header.blade.php` | Remove — not needed |
| App sidebar | `layouts/app/sidebar.blade.php` | Stock MaryUI sidebar-only layout with collapsible |
| Auth simple | `layouts/auth/simple.blade.php` | Centered form layout |
| Auth card | `layouts/auth/card.blade.php` | Remove — not needed |
| Auth split | `layouts/auth/split.blade.php` | Remove — not needed |

### Files to delete after migration

- `resources/views/flux/` directory (custom Flux icons and navlist group override)
- `resources/views/layouts/app/header.blade.php`
- `resources/views/layouts/auth/card.blade.php`
- `resources/views/layouts/auth/split.blade.php`
- Any unused Flux-specific Blade components

### Package changes

- **Install:** `robsontenorio/mary` (MaryUI) via composer
- **Install:** daisyUI via npm (MaryUI dependency)
- **Remove:** `livewire/flux` from composer
- **Update:** `resources/css/app.css` — remove Flux CSS import and `@source` directives, add daisyUI
- **Update:** `partials/head.blade.php` — remove `@fluxAppearance`
- **Update:** All layouts — remove `@fluxScripts`

## Component Mapping

| Flux | MaryUI | Notes |
|------|--------|-------|
| `<flux:input>` | `<x-input>` | |
| `<flux:button>` | `<x-button>` | Variants: use `class="btn-primary"`, `class="btn-error"`, `class="btn-ghost"` etc. |
| `<flux:modal>` | `<x-modal>` | |
| `<flux:modal.trigger>` | `@click` with `$wire` or Alpine `x-on:click` | |
| `<flux:modal.close>` | Built into `<x-modal>` | |
| `<flux:heading>` | `<x-header>` | Or plain HTML heading tags |
| `<flux:subheading>` | `<p>` with Tailwind text classes | |
| `<flux:text>` | `<p>` | |
| `<flux:checkbox>` | `<x-checkbox>` | |
| `<flux:dropdown>` | `<x-dropdown>` | |
| `<flux:menu>` | `<x-menu>` | |
| `<flux:menu.item>` | `<x-menu-item>` | |
| `<flux:menu.separator>` | `<x-menu-separator>` | |
| `<flux:sidebar>` | `<x-slot:sidebar>` inside `<x-main>` | |
| `<flux:sidebar.item>` | `<x-menu-item>` | |
| `<flux:sidebar.group>` | `<x-menu-sub>` | |
| `<flux:navlist>` | `<x-menu>` | |
| `<flux:navlist.item>` | `<x-menu-item>` | |
| `<flux:badge>` | `<x-badge>` | |
| `<flux:separator>` | `<hr>` or `<x-menu-separator>` | |
| `<flux:toast>` / `<flux:toast.group>` | `<x-toast>` | |
| `<flux:link>` | `<a>` or `<x-button link="..." class="btn-link">` | |
| `<flux:spacer>` | Tailwind `flex-1` or `grow` | |
| `<flux:callout>` | `<x-alert>` | |
| `<flux:avatar>` | `<x-avatar>` | |
| `<flux:profile>` | `<x-list-item>` | |
| `<flux:otp>` | `<x-input>` | Standard input with placeholder |
| `<flux:radio.group>` | Theme selector with `<x-radio>` or card-based picker | For appearance page |
| `<flux:brand>` / `<flux:sidebar.brand>` | Plain HTML/Blade in sidebar header | |
| `<flux:tooltip>` | `tooltip` attribute on MaryUI components | |
| `<flux:icon.*>` | `<x-icon name="o-*">` (Heroicons) | |

## Theme Selector

The appearance settings page will offer 12 daisyUI themes. Implementation:

- Store selected theme in `localStorage` (or user preference in DB if auth'd)
- Apply via `data-theme` attribute on `<html>` element
- 12 themes to include (mix of light and dark): light, dark, cupcake, bumblebee, emerald, corporate, synthwave, retro, cyberpunk, valentine, forest, dracula

## Auth Layout

Simple centered layout:

```blade
<body class="min-h-screen font-sans antialiased bg-base-200">
    <div class="flex items-center justify-center min-h-screen">
        <div class="w-full max-w-md p-6">
            {{ $slot }}
        </div>
    </div>
    <x-toast />
</body>
```

## App Layout

Stock MaryUI sidebar-only collapsible layout as documented at mary-ui.com/docs/layout. Sidebar contains:

- Brand/logo at top
- Main navigation (Dashboard)
- Settings sub-menu (Profile, Security, Appearance)
- User info + logout at bottom
- Collapsible toggle for desktop

## Testing

- Run existing test suite after migration to verify nothing breaks
- Manually verify auth flow (login, register, forgot password, 2FA)
- Manually verify settings pages (profile update, password change, appearance, delete account)
- Verify sidebar collapse/expand works on desktop
- Verify drawer works on mobile
