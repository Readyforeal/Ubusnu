<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Imports') }}</h1>
        <div class="flex gap-2 items-center">
            <x-checkbox label="Show undone" wire:model.live="showUndone" />
            <x-button label="New import" icon="lucide.upload" class="btn-primary" link="{{ route('imports.new') }}" wire:navigate />
        </div>
    </div>

    <x-table :headers="[
        ['key' => 'created_at', 'label' => 'When'],
        ['key' => 'account', 'label' => 'Account'],
        ['key' => 'filename', 'label' => 'File'],
        ['key' => 'imported', 'label' => 'Imported', 'class' => 'text-right'],
        ['key' => 'dupes', 'label' => 'Dupes', 'class' => 'text-right'],
        ['key' => 'errors', 'label' => 'Errors', 'class' => 'text-right'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-32'],
    ]" :rows="$batches">
        @scope('cell_created_at', $row)
            {{ $row->created_at->format('Y-m-d H:i') }}
        @endscope
        @scope('cell_account', $row)
            {{ $row->account?->name }}
        @endscope
        @scope('cell_imported', $row)
            {{ $row->imported_count }}
        @endscope
        @scope('cell_dupes', $row)
            {{ $row->skipped_duplicate_count }}
        @endscope
        @scope('cell_errors', $row)
            {{ $row->error_count }}
        @endscope
        @scope('cell_actions', $row)
            @if (! $row->isUndone())
                <x-button label="Undo" icon="lucide.undo-2" class="btn-ghost btn-sm" wire:click="undo({{ $row->id }})" wire:confirm="Undo this import? All transactions from this batch will be removed." />
            @else
                <x-badge value="Undone" class="badge-ghost badge-sm" />
            @endif
        @endscope
    </x-table>
</div>
