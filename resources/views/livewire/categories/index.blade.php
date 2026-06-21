<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Categories') }}</h1>
        <x-button label="New category" icon="lucide.plus" class="btn-primary" @click="$wire.startEdit(0)" />
    </div>

    @if ($editingId !== null)
        <livewire:categories.form :category-id="$editingId" :key="'cat-form-'.$editingId" />
    @endif

    <x-table :headers="[
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'keywords', 'label' => 'Keywords'],
        ['key' => 'excluded_from_totals', 'label' => 'Excluded'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20'],
    ]" :rows="$categories">
        @scope('cell_excluded_from_totals', $row)
            {{ $row->excluded_from_totals ? 'Yes' : 'No' }}
        @endscope
        @scope('cell_actions', $row)
            <x-button icon="lucide.pencil" class="btn-ghost btn-sm" @click="$wire.startEdit({{ $row->id }})" />
        @endscope
    </x-table>
</div>
