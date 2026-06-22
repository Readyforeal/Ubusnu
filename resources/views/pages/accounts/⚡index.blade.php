<?php

use App\Actions\Finance\Accounts\ComputeAccountBalance;
use App\Models\Account;
use App\Models\Transaction;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Accounts')] class extends Component {
    public ?int $editingId = null;

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
    }

    #[On('account-saved')]
    #[On('account-cancelled')]
    public function closeForm(): void
    {
        $this->editingId = null;
    }

    #[Computed]
    public function accounts(): array
    {
        $accounts = Account::active()->orderBy('name')->get();
        if ($accounts->isEmpty()) {
            return [];
        }

        $sums = Transaction::query()
            ->whereIn('account_id', $accounts->pluck('id'))
            ->selectRaw('account_id, SUM(amount_cents) as total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        return $accounts->map(fn (Account $a) => [
            'model' => $a,
            'balance_cents' => $a->starting_balance_cents + (int) ($sums[$a->id] ?? 0),
        ])->all();
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Accounts') }}</h1>
        <x-button label="New account" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
    </div>

    @if ($editingId !== null)
        <livewire:pages::accounts.form :account-id="$editingId" :key="'acct-form-'.$editingId" />
    @endif

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->accounts as $row)
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
