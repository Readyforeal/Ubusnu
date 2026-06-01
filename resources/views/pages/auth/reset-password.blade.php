<x-layouts::auth :title="__('Reset password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">

            <x-input name="email" value="{{ request('email') }}" label="{{ __('Email') }}" type="email" required autocomplete="email" inline />
            <x-input name="password" label="{{ __('Password') }}" type="password" required autocomplete="new-password" placeholder="{{ __('Password') }}" inline />
            <x-input name="password_confirmation" label="{{ __('Confirm password') }}" type="password" required autocomplete="new-password" placeholder="{{ __('Confirm password') }}" inline />

            <div class="flex items-center justify-end">
                <x-button label="{{ __('Reset password') }}" type="submit" class="btn-primary w-full" data-test="reset-password-button" />
            </div>
        </form>
    </div>
</x-layouts::auth>
