<x-card class="border border-base-300 mb-4">
    <div class="space-y-3">
        <x-input label="Name" wire:model="name" />
        <x-input label="Keywords (comma-separated)" wire:model="keywords" placeholder="safeway, save-on, walmart" />
        <x-checkbox label="Excluded from income/expense totals" wire:model="excludedFromTotals" />
        <x-input label="Color (hex)" wire:model="color" placeholder="#aabbcc" />
        <div class="flex gap-2 justify-end">
            <x-button label="Cancel" class="btn-ghost" @click="$wire.dispatch('category-cancelled')" />
            <x-button label="Save" class="btn-primary" wire:click="save" />
        </div>
    </div>
</x-card>
