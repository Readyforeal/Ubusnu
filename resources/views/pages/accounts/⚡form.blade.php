<?php

use App\Actions\Finance\Accounts\ArchiveAccount;
use App\Actions\Finance\Accounts\CreateAccount;
use App\Actions\Finance\Accounts\UpdateAccount;
use App\Models\Account;
use App\Support\Money;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public int $accountId = 0;

    #[Validate('required|string|max:120')]
    public string $name = '';

    public string $startingBalanceDollars = '0';

    public bool $countsTowardGoals = false;

    public function mount(int $accountId): void
    {
        $this->accountId = $accountId;
        if ($accountId > 0) {
            $account = Account::findOrFail($accountId);
            $this->name = $account->name;
            $this->startingBalanceDollars = number_format($account->starting_balance_cents / 100, 2, '.', '');
            $this->countsTowardGoals = $account->counts_toward_goals;
        }
    }

    public function save(): void
    {
        $this->validate();
        $cents = Money::toCents($this->startingBalanceDollars);

        if ($this->accountId > 0) {
            $account = Account::findOrFail($this->accountId);
            (new UpdateAccount)($account, [
                'name' => $this->name,
                'starting_balance_cents' => $cents,
                'counts_toward_goals' => $this->countsTowardGoals,
            ]);
        } else {
            (new CreateAccount)($this->name, $cents, $this->countsTowardGoals);
        }

        $this->dispatch('account-saved');
    }

    public function archive(): void
    {
        if ($this->accountId > 0) {
            $account = Account::findOrFail($this->accountId);
            (new ArchiveAccount)($account);
            $this->dispatch('account-saved');
        }
    }

    public function cancel(): void
    {
        $this->dispatch('account-cancelled');
    }
}; ?>

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
                <x-button label="Cancel" class="btn-ghost" wire:click="cancel" />
                <x-button label="Save" class="btn-primary" wire:click="save" />
            </div>
        </div>
    </div>
</x-card>
