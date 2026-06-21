<x-card class="border border-base-300 mb-4">
    <div class="space-y-3">
        <x-input label="Name" wire:model="name" placeholder="Tangerine Chequing" />
        <x-input label="Starting balance (dollars)" wire:model="startingBalanceDollars" placeholder="0.00" hint="Negative is OK for credit cards" />
        <x-checkbox label="Counts toward goals pool" wire:model="countsTowardGoals" />
        <div class="flex justify-between gap-2">
            @if ($accountId > 0)
                <x-button label="Archive" icon="lucide.archive" class="btn-ghost text-error" wire:click="archive" wire:confirm="Archive this account?" />
            @else
                <div></div>
            @endif
            <div class="flex gap-2">
                <x-button label="Cancel" class="btn-ghost" @click="$wire.dispatch('account-cancelled')" />
                <x-button label="Save" class="btn-primary" wire:click="save" />
            </div>
        </div>
    </div>
</x-card>
