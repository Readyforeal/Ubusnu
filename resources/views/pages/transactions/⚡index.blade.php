<?php

use App\Actions\Finance\Transactions\DeleteTransaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Transactions')] class extends Component {
    use WithPagination;

    public ?int $accountFilter = null;

    public ?int $categoryFilter = null;

    public bool $uncategorizedOnly = false;

    public string $search = '';

    public ?int $editingId = null;

    public bool $creating = false;

    public bool $formOpen = false;

    public function updatedAccountFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUncategorizedOnly(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function startCreate(): void
    {
        $this->creating = true;
        $this->editingId = null;
        $this->formOpen = true;
    }

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
        $this->creating = false;
        $this->formOpen = true;
    }

    public function updatedFormOpen(bool $value): void
    {
        if (! $value) {
            $this->editingId = null;
            $this->creating = false;
        }
    }

    public function deleteTransaction(int $id): void
    {
        $tx = Transaction::findOrFail($id);
        (new DeleteTransaction)($tx);
    }

    #[On('transaction-saved')]
    #[On('transaction-cancelled')]
    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->editingId = null;
        $this->creating = false;
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['account', 'category'])
            ->when($this->accountFilter, fn ($q) => $q->where('account_id', $this->accountFilter))
            ->when(
                $this->uncategorizedOnly,
                fn ($q) => $q->whereNull('category_id'),
                fn ($q) => $q->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            )
            ->when($this->search, fn ($q) => $q->where('description', 'like', '%'.$this->search.'%'))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(50);
    }

    public function with(): array
    {
        return [
            'accounts' => Account::active()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-2xl font-semibold">{{ __('Transactions') }}</h1>
        <x-button label="New transaction" icon="lucide.plus" class="btn-primary" wire:click="startCreate" />
    </div>

    <div class="grid gap-3 md:grid-cols-3">
        <x-input placeholder="Search description…" wire:model.live.debounce.300ms="search" icon="lucide.search" />
        <x-select placeholder="All accounts" :options="$accounts" option-label="name" option-value="id" wire:model.live="accountFilter" />
        <x-select placeholder="All categories" :options="$categories" option-label="name" option-value="id" wire:model.live="categoryFilter" :disabled="$uncategorizedOnly" />
    </div>

    <div class="flex items-center gap-2 text-sm">
        <x-checkbox label="Uncategorized only" wire:model.live="uncategorizedOnly" />
    </div>

    <x-modal wire:model="formOpen" :title="$editingId > 0 ? 'Edit transaction' : 'New transaction'" box-class="max-w-2xl">
        @if ($creating || $editingId !== null)
            <livewire:pages::transactions.form :transaction-id="$editingId ?? 0" :key="'tx-form-'.($editingId ?? 'new')" />
        @endif
    </x-modal>

    <x-table :headers="[
        ['key' => 'occurred_on', 'label' => 'Date'],
        ['key' => 'account', 'label' => 'Account'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'category', 'label' => 'Category'],
        ['key' => 'amount', 'label' => 'Amount', 'class' => 'text-right'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-24'],
    ]" :rows="$this->transactions->items()">
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

    <div>{{ $this->transactions->links() }}</div>
</div>
