<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-2xl font-semibold">{{ __('Transactions') }}</h1>
        <x-button label="New transaction" icon="lucide.plus" class="btn-primary" wire:click="startCreate" />
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <x-input placeholder="Search description…" wire:model.live.debounce.300ms="search" icon="lucide.search" />
        <x-select placeholder="All accounts" :options="$accounts" option-label="name" option-value="id" wire:model.live="accountFilter" />
        <x-select placeholder="All categories" :options="$categories" option-label="name" option-value="id" wire:model.live="categoryFilter" />
    </div>

    @if ($creating || $editingId !== null)
        <livewire:transactions.form :transaction-id="$editingId ?? 0" :key="'tx-form-'.($editingId ?? 'new')" />
    @endif

    <x-table :headers="[
        ['key' => 'occurred_on', 'label' => 'Date'],
        ['key' => 'account', 'label' => 'Account'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'category', 'label' => 'Category'],
        ['key' => 'amount', 'label' => 'Amount', 'class' => 'text-right'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-24'],
    ]" :rows="$transactions->items()">
        @scope('cell_occurred_on', $row)
            {{ $row->occurred_on->format('Y-m-d') }}
        @endscope
        @scope('cell_account', $row)
            {{ $row->account?->name }}
        @endscope
        @scope('cell_category', $row)
            {{ $row->category?->name ?? '—' }}
        @endscope
        @scope('cell_amount', $row)
            <span class="font-mono {{ $row->amount_cents < 0 ? 'text-error' : 'text-success' }}">
                {{ \App\Support\Money::format($row->amount_cents) }}
            </span>
        @endscope
        @scope('cell_actions', $row)
            <div class="flex gap-1 justify-end">
                <x-button icon="lucide.pencil" class="btn-ghost btn-sm" wire:click="startEdit({{ $row->id }})" />
                <x-button icon="lucide.trash-2" class="btn-ghost btn-sm text-error" wire:click="deleteTransaction({{ $row->id }})" wire:confirm="Delete this transaction?" />
            </div>
        @endscope
    </x-table>

    <div>{{ $transactions->links() }}</div>
</div>
