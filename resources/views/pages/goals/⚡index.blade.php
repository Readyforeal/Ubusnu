<?php

use App\Actions\Finance\Goals\ComputeGoalsStatus;
use App\Actions\Finance\Goals\DeleteGoal;
use App\Models\Goal;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Goals')] class extends Component {
    public ?int $editingId = null;

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
    }

    public function deleteGoal(int $id): void
    {
        $goal = Goal::findOrFail($id);
        (new DeleteGoal)($goal);
    }

    #[On('goal-saved')]
    #[On('goal-cancelled')]
    public function closeForm(): void
    {
        $this->editingId = null;
    }

    #[Computed]
    public function status(): array
    {
        return (new ComputeGoalsStatus)();
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-2xl font-semibold">{{ __('Goals') }}</h1>
        <x-button label="New goal" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
    </div>

    <x-card class="border border-base-300">
        <div class="text-sm opacity-60">Savings pool</div>
        <div class="text-2xl font-mono">{{ \App\Support\Money::format($this->status['pool_cents']) }}</div>
        <div class="text-sm mt-2 opacity-70">
            Allocated: {{ \App\Support\Money::format($this->status['total_allocated_cents']) }} ·
            Unallocated: {{ \App\Support\Money::format($this->status['unallocated_cents']) }}
            @if ($this->status['total_priority_percentage'] > 100)
                <span class="text-warning">— priorities sum to {{ $this->status['total_priority_percentage'] }}%, over-committed by {{ $this->status['total_priority_percentage'] - 100 }}%</span>
            @elseif ($this->status['total_priority_percentage'] < 100)
                <span class="opacity-50">— {{ 100 - $this->status['total_priority_percentage'] }}% unallocated by priority</span>
            @endif
        </div>
    </x-card>

    @if ($editingId !== null)
        <livewire:pages::goals.form :goal-id="$editingId" :key="'goal-form-'.$editingId" />
    @endif

    @if (empty($this->status['goals']))
        <x-card class="border border-base-300 text-center opacity-70">
            <p>No goals yet. Click "New goal" to add your first.</p>
        </x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->status['goals'] as $g)
                @php
                    $barClass = $g['is_fully_funded'] ? 'progress-success' : ($g['funded_percentage'] >= 80 ? 'progress-warning' : 'progress-primary');
                @endphp
                <x-card class="border border-base-300" :style="$g['color'] ? 'border-left:4px solid '.$g['color'] : ''">
                    <div class="flex justify-between items-start gap-2">
                        <div class="min-w-0">
                            <div class="font-semibold truncate">{{ $g['name'] }}</div>
                            <div class="text-xs opacity-60 mt-0.5">
                                {{ $g['priority_percentage'] }}% · target {{ \App\Support\Money::format($g['target_cents']) }}
                            </div>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <x-button icon="lucide.pencil" class="btn-ghost btn-sm" wire:click="startEdit({{ $g['id'] }})" />
                            <x-button icon="lucide.trash-2" class="btn-ghost btn-sm text-error" wire:click="deleteGoal({{ $g['id'] }})" wire:confirm="Delete this goal?" />
                        </div>
                    </div>

                    <div class="mt-3">
                        <div class="flex justify-between text-sm">
                            <span class="font-mono">{{ \App\Support\Money::format($g['capped_allocation_cents']) }}</span>
                            <span class="opacity-70">{{ $g['funded_percentage'] }}%</span>
                        </div>
                        <progress class="progress {{ $barClass }} w-full h-2" value="{{ $g['funded_percentage'] }}" max="100"></progress>
                        @if ($g['is_fully_funded'])
                            <x-badge value="Funded" class="badge-success badge-sm mt-2" />
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
