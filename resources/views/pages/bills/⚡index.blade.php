<?php

use App\Actions\Finance\Bills\ComputeBillsStatus;
use App\Actions\Finance\Bills\DeleteBill;
use App\Actions\Finance\Bills\MarkBillPaidThisPeriod;
use App\Actions\Finance\Bills\RematchUnlinkedBills;
use App\Actions\Finance\Bills\UnmarkBillPaidThisPeriod;
use App\Models\Bill;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Bills')] class extends Component {
    use Toast;

    public ?int $editingId = null;

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

    public function deleteBill(int $id): void
    {
        $bill = Bill::findOrFail($id);
        (new DeleteBill)($bill);
    }

    public function markPaid(int $id): void
    {
        $bill = Bill::findOrFail($id);
        (new MarkBillPaidThisPeriod)($bill);
    }

    public function unmarkPaid(int $id): void
    {
        $bill = Bill::findOrFail($id);
        (new UnmarkBillPaidThisPeriod)($bill);
    }

    public function rematch(): void
    {
        $result = (new RematchUnlinkedBills)();

        $this->success("Linked {$result['updated']} transactions. {$result['still_unlinked']} still unlinked.");
    }

    #[On('bill-saved')]
    #[On('bill-cancelled')]
    public function closeForm(): void
    {
        $this->formOpen = false;
        $this->editingId = null;
    }

    #[Computed]
    public function status(): array
    {
        return (new ComputeBillsStatus)();
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between flex-wrap gap-2">
        <h1 class="text-2xl font-semibold">{{ __('Bills') }}</h1>
        <div class="flex gap-2">
            <x-button label="Rematch unlinked" icon="lucide.wand-2" class="btn-ghost" wire:click="rematch" wire:loading.attr="disabled" />
            <x-button label="New bill" icon="lucide.plus" class="btn-primary" wire:click="startEdit(0)" />
        </div>
    </div>

    <x-card class="border border-base-300">
        <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
                <div class="opacity-60">Upcoming this period</div>
                <div class="text-2xl font-mono">{{ \App\Support\Money::format($this->status['total_upcoming_cents']) }}</div>
            </div>
            <div>
                <div class="opacity-60">Paid this period</div>
                <div class="text-2xl font-mono text-success">{{ \App\Support\Money::format($this->status['total_paid_this_period_cents']) }}</div>
            </div>
        </div>
    </x-card>

    <x-modal wire:model="formOpen" :title="$editingId > 0 ? 'Edit bill' : 'New bill'" box-class="max-w-2xl">
        @if ($editingId !== null)
            <livewire:pages::bills.form :bill-id="$editingId" :key="'bill-form-'.$editingId" />
        @endif
    </x-modal>

    @if (empty($this->status['bills']))
        <x-card class="border border-base-300 text-center opacity-70">
            <p>No bills yet. Click "New bill" to add your first.</p>
        </x-card>
    @else
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->status['bills'] as $b)
                <x-card class="border border-base-300" :style="$b['color'] ? 'border-left:4px solid '.$b['color'] : ''">
                    <div class="flex justify-between items-start gap-2">
                        <div class="min-w-0">
                            <a href="{{ route('bills.show', $b['id']) }}" wire:navigate class="font-semibold hover:underline truncate block">{{ $b['name'] }}</a>
                            <div class="text-xs opacity-60 mt-0.5">
                                {{ ucfirst($b['cadence']) }} · {{ \App\Support\Money::format($b['expected_amount_cents']) }}
                            </div>
                            <div class="text-xs mt-2">
                                @if ($b['days_until_due'] < 0)
                                    <span class="text-error">Overdue by {{ abs($b['days_until_due']) }} day{{ abs($b['days_until_due']) === 1 ? '' : 's' }}</span>
                                @elseif ($b['days_until_due'] === 0)
                                    <span class="text-warning">Due today</span>
                                @else
                                    Due in {{ $b['days_until_due'] }} day{{ $b['days_until_due'] === 1 ? '' : 's' }} ({{ $b['next_due_date'] }})
                                @endif
                            </div>
                        </div>
                        <div class="flex gap-1 shrink-0">
                            <x-button icon="lucide.pencil" class="btn-ghost btn-sm" wire:click="startEdit({{ $b['id'] }})" />
                            <x-button icon="lucide.trash-2" class="btn-ghost btn-sm text-error" wire:click="deleteBill({{ $b['id'] }})" wire:confirm="Delete this bill?" />
                        </div>
                    </div>

                    <div class="mt-3">
                        @if ($b['is_paid_this_period'])
                            <div class="flex items-center justify-between">
                                <x-badge value="Paid ({{ $b['payment_source'] }})" class="badge-success badge-sm" />
                                <x-button label="Unmark" class="btn-ghost btn-xs" wire:click="unmarkPaid({{ $b['id'] }})" />
                            </div>
                        @else
                            <x-button label="Mark paid this period" icon="lucide.check" class="btn-ghost btn-sm w-full" wire:click="markPaid({{ $b['id'] }})" />
                        @endif
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
</div>
