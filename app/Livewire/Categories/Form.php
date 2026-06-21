<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    public int $categoryId = 0;

    #[Validate('required|string|max:80')]
    public string $name = '';

    public ?string $keywords = null;

    public bool $excludedFromTotals = false;

    public ?string $color = null;

    public function mount(int $categoryId): void
    {
        $this->categoryId = $categoryId;
        if ($categoryId > 0) {
            $cat = Category::findOrFail($categoryId);
            $this->name = $cat->name;
            $this->keywords = $cat->keywords;
            $this->excludedFromTotals = $cat->excluded_from_totals;
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
                'keywords' => $this->keywords,
                'excluded_from_totals' => $this->excludedFromTotals,
                'color' => $this->color,
            ]
        );

        $this->dispatch('category-saved');
        $this->categoryId = 0;
        $this->name = '';
        $this->keywords = null;
        $this->excludedFromTotals = false;
        $this->color = null;
    }

    public function cancel(): void
    {
        $this->dispatch('category-cancelled');
    }

    public function render()
    {
        return view('livewire.categories.form');
    }
}
