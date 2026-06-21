<?php

namespace App\Livewire\Transactions;

use App\Actions\Finance\Transactions\DeleteTransaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Transactions')]
class Index extends Component
{
    use WithPagination;

    public ?int $accountFilter = null;

    public ?int $categoryFilter = null;

    public string $search = '';

    public ?int $editingId = null;

    public bool $creating = false;

    public function updatedAccountFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCategoryFilter(): void
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
    }

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
        $this->creating = false;
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
        $this->editingId = null;
        $this->creating = false;
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        return Transaction::query()
            ->with(['account', 'category'])
            ->when($this->accountFilter, fn ($q) => $q->where('account_id', $this->accountFilter))
            ->when($this->categoryFilter, fn ($q) => $q->where('category_id', $this->categoryFilter))
            ->when($this->search, fn ($q) => $q->where('description', 'like', '%'.$this->search.'%'))
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(50);
    }

    public function render()
    {
        return view('livewire.transactions.index', [
            'transactions' => $this->transactions,
            'accounts' => Account::active()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
