<?php

use App\Actions\Finance\Income\DeleteIncomeSource;
use App\Models\IncomeSource;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Income')] class extends Component {
    public ?int $editingId = null;

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
    }

    public function deleteSource(int $id): void
    {
        $source = IncomeSource::findOrFail($id);
        (new DeleteIncomeSource)($source);
    }

    #[On('income-saved')]
    #[On('income-cancelled')]
    public function closeForm(): void
    {
        $this->editingId = null;
    }

    public function with(): array
    {
        return [
            'sources' => IncomeSource::with('account')->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-2xl font-semibold">{{ __('Income') }}</h1>
        <x-button label="New income" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
    </div>

    @if ($editingId !== null)
        <livewire:pages::income.form :source-id="$editingId" :key="'income-form-'.$editingId" />
    @endif

    @if ($sources->isEmpty())
        <x-card class="border border-base-300 text-center opacity-70">
            <p>No income sources yet. Click "New income" to add your first.</p>
        </x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($sources as $s)
                <x-card class="border border-base-300" :style="$s->color ? 'border-left:4px solid '.$s->color : ''">
                    <div class="flex justify-between items-start gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('income.show', $s) }}" wire:navigate class="font-semibold hover:underline truncate block">{{ $s->name }}</a>
                            <div class="text-xs opacity-60 mt-0.5">
                                {{ str_replace('_', ' ', ucfirst($s->cadence)) }} · {{ \App\Support\Money::format($s->expected_amount_cents) }}
                            </div>
                            <div class="text-xs mt-2">
                                Next: {{ $s->next_expected_on?->format('M j, Y') }}
                                @if ($s->account)
                                    · {{ $s->account->name }}
                                @endif
                            </div>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <x-button icon="lucide.pencil" class="btn-ghost btn-sm" wire:click="startEdit({{ $s->id }})" />
                            <x-button icon="lucide.trash-2" class="btn-ghost btn-sm text-error" wire:click="deleteSource({{ $s->id }})" wire:confirm="Delete this income source?" />
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
