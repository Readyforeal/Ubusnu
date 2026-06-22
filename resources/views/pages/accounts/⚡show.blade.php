<?php

use App\Actions\Finance\Accounts\ComputeAccountBalance;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Account')] class extends Component {
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
}; ?>

<div class="space-y-4">
    <div class="flex items-start justify-between">
        <div>
            <a href="{{ route('accounts.index') }}" wire:navigate class="text-sm opacity-70 hover:opacity-100">← Accounts</a>
            <h1 class="text-2xl font-semibold mt-1">{{ $account->name }}</h1>
            <div class="text-3xl mt-2 font-mono">{{ \App\Support\Money::format($this->balanceCents) }}</div>
        </div>
    </div>

    <livewire:pages::charts.balance-chart :account-id="$account->id" :key="'chart-acct-'.$account->id" />

    <h2 class="text-lg font-semibold mt-6">{{ __('Transactions') }}</h2>

    <x-table :headers="[
        ['key' => 'occurred_on', 'label' => 'Date'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'category', 'label' => 'Category'],
        ['key' => 'amount', 'label' => 'Amount', 'class' => 'text-right'],
    ]" :rows="$this->transactions->items()">
        @scope('cell_occurred_on', $row)
            {{ $row->occurred_on->format('Y-m-d') }}
        @endscope
        @scope('cell_category', $row)
            {{ $row->category?->name ?? '—' }}
        @endscope
        @scope('cell_amount', $row)
            <span class="font-mono {{ $row->amount_cents < 0 ? 'text-error' : 'text-success' }}">
                {{ \App\Support\Money::format($row->amount_cents) }}
            </span>
        @endscope
    </x-table>

    <div>{{ $this->transactions->links() }}</div>
</div>
