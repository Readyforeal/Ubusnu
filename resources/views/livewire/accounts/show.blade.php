<div class="space-y-4">
    <div class="flex items-start justify-between">
        <div>
            <a href="{{ route('accounts.index') }}" wire:navigate class="text-sm opacity-70 hover:opacity-100">← Accounts</a>
            <h1 class="text-2xl font-semibold mt-1">{{ $account->name }}</h1>
            <div class="text-3xl mt-2 font-mono">{{ \App\Support\Money::format($balanceCents) }}</div>
        </div>
    </div>

    <div class="h-48 rounded-xl border border-base-300 bg-base-100 flex items-center justify-center opacity-50">Chart coming in next task</div>

    <h2 class="text-lg font-semibold mt-6">{{ __('Transactions') }}</h2>

    <x-table :headers="[
        ['key' => 'occurred_on', 'label' => 'Date'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'category', 'label' => 'Category'],
        ['key' => 'amount', 'label' => 'Amount', 'class' => 'text-right'],
    ]" :rows="$transactions->items()">
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

    <div>{{ $transactions->links() }}</div>
</div>
