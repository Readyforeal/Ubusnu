<x-card class="border border-base-300 mb-4">
    <div class="grid gap-3 md:grid-cols-2">
        <x-select label="Account" :options="$accounts" option-label="name" option-value="id" placeholder="Pick an account" wire:model="accountId" />
        <x-input type="date" label="Date" wire:model="occurredOn" />
        <x-input label="Description" wire:model="description" class="md:col-span-2" />
        <x-input label="Amount (dollars, negative = out)" wire:model="amountDollars" placeholder="-12.50" />
        <x-select label="Category" :options="$categories" option-label="name" option-value="id" placeholder="Uncategorized" wire:model="categoryId" />
        <x-textarea label="Notes" wire:model="notes" class="md:col-span-2" rows="2" />
    </div>
    <div class="flex gap-2 justify-end mt-4">
        <x-button label="Cancel" class="btn-ghost" @click="$wire.dispatch('transaction-cancelled')" />
        <x-button label="Save" class="btn-primary" wire:click="save" />
    </div>
</x-card>
