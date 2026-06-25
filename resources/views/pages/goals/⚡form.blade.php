<?php

use App\Actions\Finance\Goals\CreateGoal;
use App\Actions\Finance\Goals\UpdateGoal;
use App\Models\Goal;
use App\Support\Money;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public int $goalId = 0;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('required|numeric|min:0.01')]
    public string $targetDollars = '0';

    #[Validate('required|integer|min:0|max:100')]
    public int $priorityPercentage = 0;

    public ?string $color = null;

    public ?string $notes = null;

    public function mount(int $goalId): void
    {
        $this->goalId = $goalId;
        if ($goalId > 0) {
            $goal = Goal::findOrFail($goalId);
            $this->name = $goal->name;
            $this->targetDollars = number_format($goal->target_cents / 100, 2, '.', '');
            $this->priorityPercentage = $goal->priority_percentage;
            $this->color = $goal->color;
            $this->notes = $goal->notes;
        }
    }

    public function saveGoal(): void
    {
        $this->validate();
        $cents = Money::toCents($this->targetDollars);

        if ($this->goalId > 0) {
            $goal = Goal::findOrFail($this->goalId);
            (new UpdateGoal)($goal, [
                'name' => $this->name,
                'target_cents' => $cents,
                'priority_percentage' => $this->priorityPercentage,
                'color' => $this->color,
                'notes' => $this->notes,
            ]);
        } else {
            (new CreateGoal)(
                name: $this->name,
                targetCents: $cents,
                priorityPercentage: $this->priorityPercentage,
                color: $this->color,
                notes: $this->notes,
            );
        }

        $this->dispatch('goal-saved');
    }

    public function cancel(): void
    {
        $this->dispatch('goal-cancelled');
    }
}; ?>

<div class="space-y-3">
    <x-input label="Name" wire:model="name" placeholder="Camera" />
    <x-input label="Target ($)" wire:model="targetDollars" placeholder="1500.00" />
    <x-input type="number" label="Priority %" wire:model="priorityPercentage" min="0" max="100" hint="0-100, share of the savings pool allocated to this goal" />
    <x-input label="Color (hex)" wire:model="color" placeholder="#3b82f6" />
    <x-textarea label="Notes" wire:model="notes" rows="2" />
    <div class="flex gap-2 justify-end pt-2">
        <x-button label="Cancel" class="btn-ghost" wire:click="cancel" />
        <x-button label="Save" class="btn-primary" wire:click="saveGoal" />
    </div>
</div>
