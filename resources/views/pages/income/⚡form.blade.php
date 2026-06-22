<?php

use App\Actions\Finance\Income\CreateIncomeSource;
use App\Actions\Finance\Income\UpdateIncomeSource;
use App\Models\Account;
use App\Models\Category;
use App\Models\IncomeSource;
use App\Support\Money;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public int $sourceId = 0;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|in:weekly,biweekly,semi_monthly,monthly')]
    public string $cadence = 'biweekly';

    #[Validate('required|date')]
    public string $nextExpectedOn = '';

    public ?int $primaryDayOfMonth = null;

    public ?int $secondaryDayOfMonth = null;

    #[Validate('required|numeric|min:0.01')]
    public string $expectedDollars = '0';

    public ?int $accountId = null;

    public ?int $categoryId = null;

    public ?string $matchDescription = null;

    public ?string $color = null;

    public ?string $notes = null;

    public function mount(int $sourceId): void
    {
        $this->sourceId = $sourceId;
        if ($sourceId > 0) {
            $source = IncomeSource::findOrFail($sourceId);
            $this->name = $source->name;
            $this->cadence = $source->cadence;
            $this->nextExpectedOn = $source->next_expected_on?->toDateString() ?? '';
            $this->primaryDayOfMonth = $source->primary_day_of_month;
            $this->secondaryDayOfMonth = $source->secondary_day_of_month;
            $this->expectedDollars = number_format($source->expected_amount_cents / 100, 2, '.', '');
            $this->accountId = $source->account_id;
            $this->categoryId = $source->category_id;
            $this->matchDescription = $source->match_description;
            $this->color = $source->color;
            $this->notes = $source->notes;
        }
    }

    public function saveSource(): void
    {
        $this->validate();

        if ($this->cadence === 'semi_monthly') {
            if (! $this->secondaryDayOfMonth) {
                $this->addError('secondaryDayOfMonth', 'Semi-monthly cadence requires a secondary day.');

                return;
            }
        }

        $payload = [
            'name' => $this->name,
            'cadence' => $this->cadence,
            'next_expected_on' => $this->nextExpectedOn,
            'primary_day_of_month' => $this->cadence === 'semi_monthly' ? $this->primaryDayOfMonth : null,
            'secondary_day_of_month' => $this->cadence === 'semi_monthly' ? $this->secondaryDayOfMonth : null,
            'expected_amount_cents' => Money::toCents($this->expectedDollars),
            'account_id' => $this->accountId,
            'category_id' => $this->categoryId,
            'match_description' => $this->matchDescription,
            'color' => $this->color,
            'notes' => $this->notes,
        ];

        if ($this->sourceId > 0) {
            $source = IncomeSource::findOrFail($this->sourceId);
            (new UpdateIncomeSource)($source, $payload);
        } else {
            (new CreateIncomeSource)($payload);
        }

        $this->dispatch('income-saved');
    }

    public function cancel(): void
    {
        $this->dispatch('income-cancelled');
    }

    public function with(): array
    {
        return [
            'accounts' => Account::active()->orderBy('name')->get(),
            'categories' => Category::where('kind', 'income')->orderBy('name')->get(),
            'cadences' => [
                ['id' => 'weekly', 'name' => 'Weekly'],
                ['id' => 'biweekly', 'name' => 'Biweekly'],
                ['id' => 'semi_monthly', 'name' => 'Semi-monthly'],
                ['id' => 'monthly', 'name' => 'Monthly'],
            ],
        ];
    }
}; ?>

<x-card class="border border-base-300 mb-4">
    <div class="grid gap-3 md:grid-cols-2">
        <x-input label="Name" wire:model="name" placeholder="Paycheck" class="md:col-span-2" />
        <x-select label="Cadence" :options="$cadences" option-label="name" option-value="id" wire:model.live="cadence" />
        <x-input type="date" label="Next expected on" wire:model="nextExpectedOn" />
        @if ($cadence === 'semi_monthly')
            <x-input type="number" label="Primary day of month" wire:model="primaryDayOfMonth" min="1" max="31" hint="Usually matches the day in 'Next expected on'." />
            <x-input type="number" label="Secondary day of month" wire:model="secondaryDayOfMonth" min="1" max="31" hint="The second monthly payday (e.g., 15)." />
        @endif
        <x-input label="Expected amount ($)" wire:model="expectedDollars" placeholder="2500.00" />
        <x-select label="Account" :options="$accounts" option-label="name" option-value="id" placeholder="—" wire:model="accountId" />
        <x-select label="Category" :options="$categories" option-label="name" option-value="id" placeholder="—" wire:model="categoryId" />
        <x-input label="Match description (optional)" wire:model="matchDescription" placeholder="PAYROLL" hint="Case-insensitive substring; matched against imported transaction descriptions" class="md:col-span-2" />
        <x-input label="Color (hex)" wire:model="color" placeholder="#22c55e" />
        <x-textarea label="Notes" wire:model="notes" rows="2" class="md:col-span-2" />
    </div>
    <div class="flex gap-2 justify-end mt-4">
        <x-button label="Cancel" class="btn-ghost" wire:click="cancel" />
        <x-button label="Save" class="btn-primary" wire:click="saveSource" />
    </div>
</x-card>
