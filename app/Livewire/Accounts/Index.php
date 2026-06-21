<?php

namespace App\Livewire\Accounts;

use App\Actions\Finance\Accounts\ComputeAccountBalance;
use App\Models\Account;
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
        $balance = new ComputeAccountBalance;

        return Account::active()
            ->orderBy('name')
            ->get()
            ->map(fn (Account $a) => [
                'model' => $a,
                'balance_cents' => $balance($a),
            ])->all();
    }

    public function render()
    {
        return view('livewire.accounts.index', ['accounts' => $this->accounts]);
    }
}
