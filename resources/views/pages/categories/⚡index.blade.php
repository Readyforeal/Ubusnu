<?php

use App\Actions\Finance\Categories\RecategorizeUncategorized;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Categories')] class extends Component {
    use Toast;

    public ?int $editingId = null;

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
    }

    #[On('category-saved')]
    #[On('category-cancelled')]
    public function closeForm(): void
    {
        $this->editingId = null;
    }

    public function recategorize(): void
    {
        $result = (new RecategorizeUncategorized)();

        $this->success(
            "Recategorized {$result['updated']} transactions. {$result['still_uncategorized']} still uncategorized."
        );
    }

    #[Computed]
    public function categories(): Collection
    {
        return Category::with('bucket')->orderBy('name')->get();
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-2xl font-semibold">{{ __('Categories') }}</h1>
        <div class="flex gap-2">
            <x-button label="Recategorize uncategorized" icon="lucide.wand-2" class="btn-ghost" wire:click="recategorize" wire:loading.attr="disabled" />
            <x-button label="New category" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
        </div>
    </div>

    @if ($editingId !== null)
        <livewire:pages::categories.form :category-id="$editingId" :key="'cat-form-'.$editingId" />
    @endif

    <x-table :headers="[
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'kind', 'label' => 'Kind'],
        ['key' => 'bucket', 'label' => 'Bucket'],
        ['key' => 'keywords', 'label' => 'Keywords'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20'],
    ]" :rows="$this->categories">
        @scope('cell_kind', $row)
            @if ($row->kind === 'spending')
                <x-badge value="Spending" class="badge-info badge-sm" />
            @elseif ($row->kind === 'income')
                <x-badge value="Income" class="badge-success badge-sm" />
            @else
                <x-badge value="Transfer" class="badge-ghost badge-sm" />
            @endif
        @endscope
        @scope('cell_bucket', $row)
            {{ $row->bucket?->name ?? '—' }}
        @endscope
        @scope('cell_actions', $row)
            <x-button icon="lucide.pencil" class="btn-ghost btn-sm" wire:click="startEdit({{ $row->id }})" />
        @endscope
    </x-table>
</div>
