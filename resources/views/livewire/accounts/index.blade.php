<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Accounts') }}</h1>
        <x-button label="New account" icon="lucide.plus" class="btn-primary" @click="$wire.startEdit(0)" />
    </div>

    @if ($editingId !== null)
        <livewire:accounts.form :account-id="$editingId" :key="'acct-form-'.$editingId" />
    @endif

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($accounts as $row)
            <a href="{{ route('accounts.show', $row['model']) }}" wire:navigate class="block">
                <x-card class="border border-base-300 hover:shadow-md transition-shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="font-semibold">{{ $row['model']->name }}</div>
                            <div class="text-2xl mt-1">{{ \App\Support\Money::format($row['balance_cents']) }}</div>
                        </div>
                        <x-button icon="lucide.pencil" class="btn-ghost btn-sm" @click.stop.prevent="$wire.startEdit({{ $row['model']->id }})" />
                    </div>
                    @if ($row['model']->counts_toward_goals)
                        <x-badge value="Goals pool" class="badge-info mt-2" />
                    @endif
                </x-card>
            </a>
        @endforeach
    </div>
</div>
