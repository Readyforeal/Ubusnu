<?php

use App\Actions\Finance\Imports\UndoImportBatch;
use App\Models\ImportBatch;
use App\Models\Transaction;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Import batch')] class extends Component {
    public ImportBatch $batch;

    public function mount(ImportBatch $batch): void
    {
        $this->batch = $batch->load(['account', 'user']);
    }

    public function undo(): void
    {
        (new UndoImportBatch)($this->batch);
        $this->batch->refresh();
    }

    #[Computed]
    public function transactions()
    {
        return Transaction::query()
            ->withTrashed()
            ->with('category')
            ->where('import_batch_id', $this->batch->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(50);
    }
}; ?>

<div class="space-y-4">
    <div>
        <a href="{{ route('imports.index') }}" wire:navigate class="text-sm opacity-70 hover:opacity-100">← Imports</a>
        <h1 class="text-2xl font-semibold mt-1">{{ $batch->filename }}</h1>
        <div class="text-sm opacity-70 mt-1">
            Imported {{ $batch->created_at->format('Y-m-d H:i') }} into {{ $batch->account?->name }} by {{ $batch->user?->name ?? '—' }}
        </div>
    </div>

    <div class="grid gap-3 md:grid-cols-4">
        <x-card class="border border-base-300">
            <div class="text-xs opacity-60">Total rows</div>
            <div class="text-2xl font-mono">{{ $batch->row_count }}</div>
        </x-card>
        <x-card class="border border-base-300">
            <div class="text-xs opacity-60">Imported</div>
            <div class="text-2xl font-mono text-success">{{ $batch->imported_count }}</div>
        </x-card>
        <x-card class="border border-base-300">
            <div class="text-xs opacity-60">Duplicates skipped</div>
            <div class="text-2xl font-mono text-warning">{{ $batch->skipped_duplicate_count }}</div>
        </x-card>
        <x-card class="border border-base-300">
            <div class="text-xs opacity-60">Errors</div>
            <div class="text-2xl font-mono text-error">{{ $batch->error_count }}</div>
        </x-card>
    </div>

    <div class="flex gap-2">
        @if ($batch->isUndone())
            <x-badge value="Undone {{ $batch->undone_at->format('Y-m-d H:i') }}" class="badge-ghost" />
        @else
            <x-button label="Undo this import" icon="lucide.undo-2" class="btn-error btn-outline" wire:click="undo" wire:confirm="Undo this import? All transactions from this batch will be soft-deleted." />
        @endif
    </div>

    <h2 class="text-lg font-semibold mt-6">{{ __('Transactions in this batch') }}</h2>

    <x-table :headers="[
        ['key' => 'occurred_on', 'label' => 'Date'],
        ['key' => 'description', 'label' => 'Description'],
        ['key' => 'category', 'label' => 'Category'],
        ['key' => 'amount', 'label' => 'Amount', 'class' => 'text-right'],
        ['key' => 'state', 'label' => 'State'],
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
        @scope('cell_state', $row)
            @if ($row->trashed())
                <x-badge value="Deleted" class="badge-ghost badge-sm" />
            @else
                <x-badge value="Active" class="badge-success badge-sm" />
            @endif
        @endscope
    </x-table>

    <div>{{ $this->transactions->links() }}</div>
</div>
