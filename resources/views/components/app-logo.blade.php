@props([
    'sidebar' => false,
])

<div {{ $attributes->merge(['class' => 'flex items-center gap-2']) }}>
    <x-icon name="lucide.cat" class="size-7" />
    <span class="font-bold text-lg text-nowrap mary-hideable">{{ config('app.name', 'Laravel') }}</span>
</div>
