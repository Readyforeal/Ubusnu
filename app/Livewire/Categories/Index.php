<?php

namespace App\Livewire\Categories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Categories')]
class Index extends Component
{
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

    public function render()
    {
        return view('livewire.categories.index', [
            'categories' => $this->categories,
        ]);
    }
}
