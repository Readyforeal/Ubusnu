<?php

use App\Actions\Finance\Bills\CreateBill;
use App\Actions\Finance\Bills\UpdateBill;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Support\Money;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public int $billId = 0;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|in:monthly,annual')]
    public string $cadence = 'monthly';

    #[Validate('required|integer|min:1|max:31')]
    public int $dueDayOfMonth = 1;

    public ?int $dueMonthOfYear = null;

    #[Validate('required|numeric|min:0.01')]
    public string $expectedDollars = '0';

    public ?int $accountId = null;

    public ?int $categoryId = null;

    public ?string $matchDescription = null;

    public ?string $color = null;

    public ?string $notes = null;

    public function mount(int $billId): void
    {
        $this->billId = $billId;
        if ($billId > 0) {
            $bill = Bill::findOrFail($billId);
            $this->name = $bill->name;
            $this->cadence = $bill->cadence;
            $this->dueDayOfMonth = $bill->due_day_of_month;
            $this->dueMonthOfYear = $bill->due_month_of_year;
            $this->expectedDollars = number_format($bill->expected_amount_cents / 100, 2, '.', '');
            $this->accountId = $bill->account_id;
            $this->categoryId = $bill->category_id;
            $this->matchDescription = $bill->match_description;
            $this->color = $bill->color;
            $this->notes = $bill->notes;
        }
    }

    public function saveBill(): void
    {
        $this->validate();

        if ($this->cadence === 'annual' && ! $this->dueMonthOfYear) {
            $this->addError('dueMonthOfYear', 'Annual bills require a month.');

            return;
        }

        $payload = [
            'name' => $this->name,
            'cadence' => $this->cadence,
            'due_day_of_month' => $this->dueDayOfMonth,
            'due_month_of_year' => $this->cadence === 'annual' ? $this->dueMonthOfYear : null,
            'expected_amount_cents' => Money::toCents($this->expectedDollars),
            'account_id' => $this->accountId,
            'category_id' => $this->categoryId,
            'match_description' => $this->matchDescription,
            'color' => $this->color,
            'notes' => $this->notes,
        ];

        if ($this->billId > 0) {
            $bill = Bill::findOrFail($this->billId);
            (new UpdateBill)($bill, $payload);
        } else {
            (new CreateBill)($payload);
        }

        $this->dispatch('bill-saved');
    }

    public function cancel(): void
    {
        $this->dispatch('bill-cancelled');
    }

    public function with(): array
    {
        return [
            'accounts' => Account::active()->orderBy('name')->get(),
            'categories' => Category::orderBy('name')->get(),
            'months' => collect(range(1, 12))->map(fn ($m) => ['id' => $m, 'name' => \Carbon\CarbonImmutable::create(2026, $m, 1)->format('F')]),
        ];
    }
}; ?>

<x-card class="border border-base-300 mb-4">
    <div class="grid gap-3 md:grid-cols-2">
        <x-input label="Name" wire:model="name" placeholder="Mortgage" class="md:col-span-2" />
        <x-radio label="Cadence" :options="[
            ['id' => 'monthly', 'name' => 'Monthly'],
            ['id' => 'annual', 'name' => 'Annual'],
        ]" wire:model.live="cadence" />
        <x-input type="number" label="Due day of month (1-31)" wire:model="dueDayOfMonth" min="1" max="31" />
        @if ($cadence === 'annual')
            <x-select label="Due month" :options="$months" option-label="name" option-value="id" wire:model="dueMonthOfYear" placeholder="Pick a month" />
        @endif
        <x-input label="Expected amount ($)" wire:model="expectedDollars" placeholder="2300.00" />
        <x-select label="Account (optional)" :options="$accounts" option-label="name" option-value="id" placeholder="—" wire:model="accountId" />
        <x-select label="Category (optional)" :options="$categories" option-label="name" option-value="id" placeholder="—" wire:model="categoryId" />
        <x-input label="Match description (optional)" wire:model="matchDescription" placeholder="US BANK HOME MTG" hint="Case-insensitive substring; matched against imported transaction descriptions" class="md:col-span-2" />
        <x-input label="Color (hex)" wire:model="color" placeholder="#ef4444" />
        <x-textarea label="Notes" wire:model="notes" rows="2" class="md:col-span-2" />
    </div>
    <div class="flex gap-2 justify-end mt-4">
        <x-button label="Cancel" class="btn-ghost" wire:click="cancel" />
        <x-button label="Save" class="btn-primary" wire:click="saveBill" />
    </div>
</x-card>
