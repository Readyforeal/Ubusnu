<?php

use App\Actions\Finance\Budgets\CreateBucket;
use App\Actions\Finance\Budgets\UpdateBucket;
use App\Models\Bucket;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component {
    public int $bucketId = 0;

    #[Validate('required|string|max:80')]
    public string $name = '';

    #[Validate('required|integer|min:0|max:100')]
    public int $targetPercentage = 0;

    public ?string $color = null;

    public function mount(int $bucketId): void
    {
        $this->bucketId = $bucketId;
        if ($bucketId > 0) {
            $bucket = Bucket::findOrFail($bucketId);
            $this->name = $bucket->name;
            $this->targetPercentage = $bucket->target_percentage;
            $this->color = $bucket->color;
        }
    }

    public function saveBucket(): void
    {
        $this->validate();

        if ($this->bucketId > 0) {
            $bucket = Bucket::findOrFail($this->bucketId);
            (new UpdateBucket)($bucket, [
                'name' => $this->name,
                'target_percentage' => $this->targetPercentage,
                'color' => $this->color,
            ]);
        } else {
            (new CreateBucket)($this->name, $this->targetPercentage, $this->color);
        }

        $this->dispatch('bucket-saved');
    }

    public function cancel(): void
    {
        $this->dispatch('bucket-cancelled');
    }
}; ?>

<div class="space-y-3">
    <x-input label="Name" wire:model="name" placeholder="Essentials" />
    <x-input type="number" label="Target percentage" wire:model="targetPercentage" min="0" max="100" hint="0-100, percentage of monthly income" />
    <x-input label="Color (hex)" wire:model="color" placeholder="#22c55e" />
    <div class="flex gap-2 justify-end pt-2">
        <x-button label="Cancel" class="btn-ghost" wire:click="cancel" />
        <x-button label="Save" class="btn-primary" wire:click="saveBucket" />
    </div>
</div>
