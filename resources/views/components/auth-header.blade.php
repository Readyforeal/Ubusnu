@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <h1 class="text-xl font-bold">{{ $title }}</h1>
    <p class="text-sm text-base-content/60 mt-1">{{ $description }}</p>
</div>
