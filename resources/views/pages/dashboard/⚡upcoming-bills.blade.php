<?php

use App\Actions\Finance\Bills\ComputeBillsStatus;
use Livewire\Component;

new class extends Component {
    /** @var array<int, array<string, mixed>> */
    public array $upcoming = [];

    public function mount(): void
    {
        $status = (new ComputeBillsStatus)();
        $this->upcoming = collect($status['bills'])
            ->filter(fn ($b) => $b['days_until_due'] <= 14)
            ->values()
            ->all();
    }
}; ?>

<div>
    @if (! empty($upcoming))
        <x-card class="border border-base-300">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold">{{ __('Upcoming bills') }}</h2>
                <a href="{{ route('bills.index') }}" wire:navigate class="text-sm opacity-70 hover:opacity-100">All bills →</a>
            </div>

            <div class="space-y-2">
                @foreach ($upcoming as $b)
                    <div class="flex justify-between items-center text-sm">
                        <div>
                            <a href="{{ route('bills.show', $b['id']) }}" wire:navigate class="font-medium hover:underline">{{ $b['name'] }}</a>
                            <span class="opacity-60 ml-2">
                                @if ($b['days_until_due'] < 0)
                                    Overdue by {{ abs($b['days_until_due']) }} day{{ abs($b['days_until_due']) === 1 ? '' : 's' }}
                                @elseif ($b['days_until_due'] === 0)
                                    Due today
                                @else
                                    Due {{ $b['next_due_date'] }} ({{ $b['days_until_due'] }} day{{ $b['days_until_due'] === 1 ? '' : 's' }})
                                @endif
                            </span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono">{{ \App\Support\Money::format($b['expected_amount_cents']) }}</span>
                            @if ($b['is_paid_this_period'])
                                <x-badge value="Paid" class="badge-success badge-xs" />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif
</div>
