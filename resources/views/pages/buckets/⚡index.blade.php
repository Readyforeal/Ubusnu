<?php

use App\Actions\Finance\Budgets\DeleteBucket;
use App\Models\AppSetting;
use App\Models\Bucket;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Budget')] class extends Component {
    public ?int $editingId = null;

    public string $incomeTargetDollars = '0';

    public function mount(): void
    {
        $cents = AppSetting::current()->monthly_income_target_cents;
        $this->incomeTargetDollars = number_format($cents / 100, 2, '.', '');
    }

    public bool $formOpen = false;

    public function startEdit(int $id): void
    {
        $this->editingId = $id;
        $this->formOpen = true;
    }

    public function updatedFormOpen(bool $value): void
    {
        if (! $value) {
            $this->editingId = null;
        }
    }

    public function deleteBucket(int $id): void
    {
        $bucket = Bucket::findOrFail($id);
        (new DeleteBucket)($bucket);
    }

    public function applyIncomeTarget(): void
    {
        $cents = Money::toCents($this->incomeTargetDollars);
        AppSetting::current()->update(['monthly_income_target_cents' => $cents]);
    }

    #[On('bucket-saved')]
    #[On('bucket-cancelled')]
    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->editingId = null;
    }

    #[Computed]
    public function buckets(): Collection
    {
        return Bucket::query()->withCount('categories')->orderBy('sort_order')->orderBy('id')->get();
    }

    #[Computed]
    public function totalPercentage(): int
    {
        return (int) Bucket::query()->sum('target_percentage');
    }

    #[Computed]
    public function incomeTargetCents(): int
    {
        return AppSetting::current()->monthly_income_target_cents;
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-2xl font-semibold">{{ __('Budget') }}</h1>
        <x-button label="New bucket" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
    </div>

    <x-card class="border border-base-300">
        <div class="flex items-end gap-3">
            <x-input label="Monthly income target ($)" wire:model="incomeTargetDollars" />
            <x-button label="Save target" class="btn-primary" wire:click="applyIncomeTarget" />
        </div>
        <div class="text-sm mt-3 opacity-70">
            Allocated: {{ $this->totalPercentage }}% of {{ \App\Support\Money::format($this->incomeTargetCents) }}
            @if ($this->totalPercentage !== 100)
                <span class="text-warning">— buckets sum to {{ $this->totalPercentage }}%, not 100%</span>
            @endif
        </div>
    </x-card>

    <x-modal wire:model="formOpen" :title="$editingId > 0 ? 'Edit bucket' : 'New bucket'">
        @if ($editingId !== null)
            <livewire:pages::buckets.form :bucket-id="$editingId" :key="'bucket-form-'.$editingId" />
        @endif
    </x-modal>

    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
        @foreach ($this->buckets as $bucket)
            <x-card class="border border-base-300" :style="$bucket->color ? 'border-left:4px solid '.$bucket->color : ''">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="font-semibold">{{ $bucket->name }}</div>
                        <div class="text-2xl mt-1 font-mono">{{ \App\Support\Money::format($bucket->targetCents($this->incomeTargetCents)) }}</div>
                        <div class="text-xs opacity-60">{{ $bucket->target_percentage }}% · {{ $bucket->categories_count }} categor{{ $bucket->categories_count === 1 ? 'y' : 'ies' }}</div>
                    </div>
                    <div class="flex gap-1">
                        <x-button icon="lucide.pencil" class="btn-ghost btn-sm" wire:click="startEdit({{ $bucket->id }})" />
                        <x-button icon="lucide.trash-2" class="btn-ghost btn-sm text-error" wire:click="deleteBucket({{ $bucket->id }})" wire:confirm="Delete this bucket? Categories using it will be unassigned." />
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>
</div>
