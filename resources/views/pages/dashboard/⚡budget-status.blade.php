<?php

use App\Actions\Finance\Budgets\ComputeMonthlyBudgetStatus;
use App\Support\Money;
use Livewire\Component;

new class extends Component {
    /** @var array<string, mixed> */
    public array $status = [];

    public function mount(): void
    {
        $this->status = (new ComputeMonthlyBudgetStatus)();
    }
}; ?>

<x-card class="border border-base-300">
    <div class="flex justify-between items-center mb-3">
        <h2 class="text-lg font-semibold">{{ __('This month') }}</h2>
        <div class="text-sm">
            <span class="opacity-60">{{ __('Income') }}:</span>
            <span class="font-mono">{{ \App\Support\Money::format($status['income_actual_cents']) }}</span>
            <span class="opacity-60">/ {{ \App\Support\Money::format($status['income_target_cents']) }}</span>
        </div>
    </div>

    <div class="space-y-3">
        @foreach ($status['buckets'] as $b)
            @php
                $pct = $b['target_cents'] > 0 ? max(0, min(150, (int) round($b['actual_cents'] / $b['target_cents'] * 100))) : 0;
                $barClass = $b['over_target'] ? 'progress-error' : ($pct >= 80 ? 'progress-warning' : 'progress-primary');
                $barWidth = $pct > 100 ? 100 : $pct;
            @endphp
            <div>
                <div class="flex justify-between text-sm">
                    <span>{{ $b['name'] }}</span>
                    <span class="font-mono">
                        {{ \App\Support\Money::format(max(0, $b['actual_cents'])) }}
                        <span class="opacity-50">/ {{ \App\Support\Money::format($b['target_cents']) }}</span>
                        <span class="opacity-50">({{ $pct }}%)</span>
                    </span>
                </div>
                <progress class="progress {{ $barClass }} w-full h-2" value="{{ $barWidth }}" max="100"></progress>
            </div>
        @endforeach

        @if ($status['unassigned_actual_cents'] > 0)
            <div>
                <div class="flex justify-between text-sm">
                    <span class="opacity-70">{{ __('Unassigned') }}</span>
                    <span class="font-mono opacity-70">{{ \App\Support\Money::format($status['unassigned_actual_cents']) }}</span>
                </div>
                <progress class="progress progress-ghost w-full h-2" value="0" max="100"></progress>
            </div>
        @endif
    </div>
</x-card>
