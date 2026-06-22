<?php

use App\Actions\Finance\Goals\ComputeGoalsStatus;
use Livewire\Component;

new class extends Component {
    /** @var array<string, mixed> */
    public array $status = [];

    public function mount(): void
    {
        $this->status = (new ComputeGoalsStatus)();
    }
}; ?>

<div>
    @if (! empty($status['goals']))
        <x-card class="border border-base-300">
            <div class="flex justify-between items-center mb-3">
                <h2 class="text-lg font-semibold">{{ __('Goals') }}</h2>
                <div class="text-sm">
                    <span class="opacity-60">{{ __('Savings pool') }}:</span>
                    <span class="font-mono">{{ \App\Support\Money::format($status['pool_cents']) }}</span>
                </div>
            </div>

            <div class="space-y-3">
                @foreach ($status['goals'] as $g)
                    @php
                        $barClass = $g['is_fully_funded'] ? 'progress-success' : ($g['funded_percentage'] >= 80 ? 'progress-warning' : 'progress-primary');
                    @endphp
                    <div>
                        <div class="flex justify-between text-sm">
                            <span>
                                {{ $g['name'] }}
                                @if ($g['is_fully_funded'])
                                    <x-badge value="Funded" class="badge-success badge-xs ml-1" />
                                @endif
                            </span>
                            <span class="font-mono">
                                {{ \App\Support\Money::format($g['capped_allocation_cents']) }}
                                <span class="opacity-50">/ {{ \App\Support\Money::format($g['target_cents']) }}</span>
                                <span class="opacity-50">({{ $g['funded_percentage'] }}%)</span>
                            </span>
                        </div>
                        <progress class="progress {{ $barClass }} w-full h-2" value="{{ $g['funded_percentage'] }}" max="100"></progress>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif
</div>
