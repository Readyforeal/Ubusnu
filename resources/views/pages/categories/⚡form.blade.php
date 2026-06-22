<?php

use App\Models\Category;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public int $categoryId = 0;

    #[Validate('required|string|max:80')]
    public string $name = '';

    public string $kind = 'spending';

    public ?string $keywords = null;

    public ?string $color = null;

    public function mount(int $categoryId): void
    {
        $this->categoryId = $categoryId;
        if ($categoryId > 0) {
            $cat = Category::findOrFail($categoryId);
            $this->name = $cat->name;
            $this->kind = $cat->kind;
            $this->keywords = $cat->keywords;
            $this->color = $cat->color;
        }
    }

    public function save(): void
    {
        $this->validate();

        Category::updateOrCreate(
            ['id' => $this->categoryId > 0 ? $this->categoryId : null],
            [
                'name' => $this->name,
                'kind' => $this->kind,
                'keywords' => $this->keywords,
                'color' => $this->color,
            ]
        );

        $this->dispatch('category-saved');
    }

    public function cancel(): void
    {
        $this->dispatch('category-cancelled');
    }
}; ?>

<x-card class="border border-base-300 mb-4">
    <div class="space-y-3">
        <x-input label="Name" wire:model="name" />
        <x-input label="Keywords (comma-separated)" wire:model="keywords" placeholder="safeway, save-on, walmart" />
        <x-input label="Color (hex)" wire:model="color" placeholder="#aabbcc" />
        <div class="flex gap-2 justify-end">
            <x-button label="Cancel" class="btn-ghost" wire:click="cancel" />
            <x-button label="Save" class="btn-primary" wire:click="save" />
        </div>
    </div>
</x-card>
