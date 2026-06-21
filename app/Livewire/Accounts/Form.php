<?php

namespace App\Livewire\Accounts;

use App\Actions\Finance\Accounts\ArchiveAccount;
use App\Actions\Finance\Accounts\CreateAccount;
use App\Actions\Finance\Accounts\UpdateAccount;
use App\Models\Account;
use App\Support\Money;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
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

    public function render()
    {
        return view('livewire.accounts.form');
    }
}
