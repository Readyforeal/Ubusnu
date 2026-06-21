<?php

namespace App\Livewire\Transactions;

use App\Actions\Finance\Transactions\CreateTransaction;
use App\Actions\Finance\Transactions\UpdateTransaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Support\Money;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    public int $transactionId = 0;

    #[Validate('required|integer|exists:accounts,id')]
    public ?int $accountId = null;

    #[Validate('required|date')]
    public string $occurredOn = '';

    #[Validate('required|string|max:500')]
    public string $description = '';

    #[Validate('required|string')]
    public string $amountDollars = '';

    public ?int $categoryId = null;

    public ?string $notes = null;

    public function mount(int $transactionId): void
    {
        $this->transactionId = $transactionId;
        if ($transactionId > 0) {
            $tx = Transaction::findOrFail($transactionId);
            $this->accountId = $tx->account_id;
            $this->occurredOn = $tx->occurred_on->format('Y-m-d');
            $this->description = $tx->description;
            $this->amountDollars = number_format($tx->amount_cents / 100, 2, '.', '');
            $this->categoryId = $tx->category_id;
            $this->notes = $tx->notes;
        } else {
            $this->occurredOn = now()->toDateString();
        }
    }

    public function save(): void
    {
        $this->validate();
        $cents = Money::toCents($this->amountDollars);

        if ($this->transactionId > 0) {
            $tx = Transaction::findOrFail($this->transactionId);
            (new UpdateTransaction)($tx, [
                'occurred_on' => $this->occurredOn,
                'description' => $this->description,
                'amount_cents' => $cents,
                'category_id' => $this->categoryId,
                'notes' => $this->notes,
            ]);
        } else {
            $account = Account::findOrFail($this->accountId);
            (new CreateTransaction)(
                account: $account,
                occurredOn: $this->occurredOn,
                description: $this->description,
                amountCents: $cents,
                categoryId: $this->categoryId,
                notes: $this->notes,
            );
        }

        $this->dispatch('transaction-saved');
    }

    public function render()
    {
        return view('livewire.transactions.form', [
            'accounts' => Account::active()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
        ]);
    }
}
