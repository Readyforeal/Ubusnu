<?php

use App\Models\IncomeSource;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Income source')] class extends Component {
    use WithPagination;

    public IncomeSource $source;

    public function mount(IncomeSource $source): void
    {
        $this->source = $source->load(['account', 'category']);
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['account'])
            ->where('income_source_id', $this->source->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(25);
    }
}; ?>

<div class="space-y-4">
    <div>
        <a href="{{ route('income.index') }}" wire:navigate class="text-sm opacity-70 hover:opacity-100">← Income</a>
        <h1 class="text-2xl font-semibold mt-1">{{ $source->name }}</h1>
        <div class="text-sm opacity-70 mt-1">
            {{ str_replace('_', ' ', ucfirst($source->cadence)) }} ·
            Next {{ $source->next_expected_on?->format('M j, Y') }} ·
            {{ \App\Support\Money::format($source->expected_amount_cents) }}
            @if ($source->account)
                · into {{ $source->account->name }}
            @endif
            @if ($source->category)
                · {{ $source->category->name }}
            @endif
        </div>
        @if ($source->match_description)
            <div class="text-xs opacity-60 mt-1">Matches: <span class="font-mono">{{ $source->match_description }}</span></div>
        @endif
    </div>

    <h2 class="text-lg font-semibold mt-6">{{ __('Matched deposits') }}</h2>

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
            <span class="font-mono text-success">{{ \App\Support\Money::format($row->amount_cents) }}</span>
        @endscope
    </x-table>

    <div>{{ $this->transactions->links() }}</div>
</div>
