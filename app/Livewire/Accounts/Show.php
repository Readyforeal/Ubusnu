<?php

namespace App\Livewire\Accounts;

use App\Actions\Finance\Accounts\ComputeAccountBalance;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Account')]
class Show extends Component
{
    use WithPagination;

    public Account $account;

    public function mount(Account $account): void
    {
        $this->account = $account;
    }

    #[Computed]
    public function balanceCents(): int
    {
        return (new ComputeAccountBalance)($this->account);
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return Transaction::query()
            ->with('category')
            ->where('account_id', $this->account->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(50);
    }

    public function render()
    {
        return view('livewire.accounts.show', [
            'balanceCents' => $this->balanceCents,
            'transactions' => $this->transactions,
        ]);
    }
}
