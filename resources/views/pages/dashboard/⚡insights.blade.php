<?php

use App\Actions\Coach\BuildInsights;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    #[Computed]
    public function insights(): array
    {
        return (new BuildInsights)();
    }

    public function severityClasses(string $severity): string
    {
        return match ($severity) {
            'critical' => 'border-error/30 bg-error/5',
            'warning' => 'border-warning/30 bg-warning/5',
            'positive' => 'border-success/30 bg-success/5',
            default => 'border-base-300 bg-base-200/30',
        };
    }
}; ?>

<x-card class="border border-base-300" title="Insights">
    @if (empty($this->insights))
        <p class="text-sm opacity-60">Nothing to flag right now. Keep importing transactions to surface patterns.</p>
    @else
        <div class="grid gap-2 md:grid-cols-2">
            @foreach ($this->insights as $insight)
                <a href="{{ $insight->suggestedPrompt ? route('chat.index', ['prompt' => $insight->suggestedPrompt]) : route('chat.index') }}" wire:navigate class="block p-3 rounded-lg border {{ $this->severityClasses($insight->severity) }} hover:opacity-90">
                    <div class="text-sm font-semibold">{{ $insight->headline }}</div>
                    <div class="text-xs opacity-70 mt-1">{{ $insight->detail }}</div>
                </a>
            @endforeach
        </div>
    @endif
</x-card>
