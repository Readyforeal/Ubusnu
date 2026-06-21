<?php

namespace App\Livewire\Accounts;

use App\Models\Account;
use App\Models\Transaction;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Accounts')]
class Index extends Component
{
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

    public function render()
    {
        return view('livewire.accounts.index', ['accounts' => $this->accounts]);
    }
}
