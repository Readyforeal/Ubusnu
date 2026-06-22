<?php

use App\Models\Bill;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Bill')] class extends Component {
    use WithPagination;

    public Bill $bill;

    public function mount(Bill $bill): void
    {
        $this->bill = $bill->load(['account', 'category']);
    }

    public function removePeriod(string $period): void
    {
        $this->bill->removeManuallyMarkedPeriod($period);
        $this->bill->refresh();
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['account', 'category'])
            ->where('bill_id', $this->bill->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(25);
    }
}; ?>

<div class="space-y-4">
    <div>
        <a href="{{ route('bills.index') }}" wire:navigate class="text-sm opacity-70 hover:opacity-100">← Bills</a>
        <h1 class="text-2xl font-semibold mt-1">{{ $bill->name }}</h1>
        <div class="text-sm opacity-70 mt-1">
            {{ ucfirst($bill->cadence) }} ·
            Due {{ $bill->cadence === 'annual' ? \Carbon\CarbonImmutable::create(2026, $bill->due_month_of_year ?? 1, 1)->format('F') . ' ' . $bill->due_day_of_month : 'day ' . $bill->due_day_of_month }} ·
            {{ \App\Support\Money::format($bill->expected_amount_cents) }}
            @if ($bill->account)
                · paid from {{ $bill->account->name }}
            @endif
            @if ($bill->category)
                · {{ $bill->category->name }}
            @endif
        </div>
        @if ($bill->match_description)
            <div class="text-xs opacity-60 mt-1">Matches: <span class="font-mono">{{ $bill->match_description }}</span></div>
        @endif
    </div>

    @if (count($bill->manuallyMarkedPeriods()) > 0)
        <x-card class="border border-base-300">
            <h2 class="text-sm font-semibold mb-2">Manually-marked paid periods</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($bill->manuallyMarkedPeriods() as $period)
                    <div class="flex items-center gap-1 px-2 py-1 rounded bg-base-200 text-sm">
                        <span class="font-mono">{{ $period }}</span>
                        <button wire:click="removePeriod('{{ $period }}')" class="text-error hover:opacity-80">✕</button>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    <h2 class="text-lg font-semibold mt-6">{{ __('Linked transactions') }}</h2>

    <x-table :headers="[
        ['key' => 'occurred_on', 'label' => 'Date'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'account', 'label' => 'Account'],
        ['key' => 'amount', 'label' => 'Amount', 'class' => 'text-right'],
    ]" :rows="$this->transactions->items()">
        @scope('cell_occurred_on', $row)
            {{ $row->occurred_on->format('Y-m-d') }}
        @endscope
        @scope('cell_account', $row)
            {{ $row->account?->name }}
        @endscope
        @scope('cell_amount', $row)
            <span class="font-mono {{ $row->amount_cents < 0 ? 'text-error' : 'text-success' }}">
                {{ \App\Support\Money::format($row->amount_cents) }}
            </span>
        @endscope
    </x-table>

    <div>{{ $this->transactions->links() }}</div>
</div>
