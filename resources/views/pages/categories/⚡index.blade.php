<?php

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Categories')] class extends Component {
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

    #[Computed]
    public function categories(): Collection
    {
        return Category::orderBy('name')->get();
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ __('Categories') }}</h1>
        <x-button label="New category" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
    </div>

    @if ($editingId !== null)
        <livewire:pages::categories.form :category-id="$editingId" :key="'cat-form-'.$editingId" />
    @endif

    <x-table :headers="[
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'keywords', 'label' => 'Keywords'],
        ['key' => 'excluded_from_totals', 'label' => 'Excluded'],
        ['key' => 'actions', 'label' => '', 'class' => 'w-20'],
    ]" :rows="$this->categories">
        @scope('cell_excluded_from_totals', $row)
            {{ $row->excluded_from_totals ? 'Yes' : 'No' }}
        @endscope
        @scope('cell_actions', $row)
            <x-button icon="lucide.pencil" class="btn-ghost btn-sm" wire:click="startEdit({{ $row->id }})" />
        @endscope
    </x-table>
</div>
