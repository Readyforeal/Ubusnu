<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <x-menu activate-by-route>
            <x-menu-item title="{{ __('Profile') }}" icon="lucide.user" link="{{ route('profile.edit') }}" wire:navigate />
            <x-menu-item title="{{ __('Security') }}" icon="lucide.shield-check" link="{{ route('security.edit') }}" wire:navigate />
            <x-menu-item title="{{ __('Appearance') }}" icon="lucide.palette" link="{{ route('appearance.edit') }}" wire:navigate />
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
