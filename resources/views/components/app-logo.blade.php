@props([
    'sidebar' => false,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <x-app-logo-icon class="size-7 fill-current" />
    <span class="font-bold text-lg text-nowrap mary-hideable">{{ config('app.name', 'Laravel') }}</span>
</div>
