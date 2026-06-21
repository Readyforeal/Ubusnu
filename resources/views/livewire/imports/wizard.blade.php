<div class="space-y-4 max-w-3xl mx-auto">
    <h1 class="text-2xl font-semibold">{{ __('Import CSV') }}</h1>

    <ul class="steps w-full">
        <li class="step {{ in_array($step, ['upload','map','preview','done']) ? 'step-primary' : '' }}">Upload</li>
        <li class="step {{ in_array($step, ['map','preview','done']) ? 'step-primary' : '' }}">Map columns</li>
        <li class="step {{ in_array($step, ['preview','done']) ? 'step-primary' : '' }}">Preview</li>
        <li class="step {{ $step === 'done' ? 'step-primary' : '' }}">Done</li>
    </ul>

    @if ($step === 'upload')
        <x-card class="border border-base-300">
            <div class="space-y-3">
                <x-select label="Target account" :options="$accounts" option-label="name" option-value="id" placeholder="Pick an account" wire:model="accountId" />
                <x-file label="CSV file" wire:model="upload" accept=".csv,text/csv,text/plain" hint="Max 10 MB" />
                <div class="flex justify-end">
                    <x-button label="Next" class="btn-primary" wire:click="proceedFromUpload" spinner="proceedFromUpload" />
                </div>
            </div>
        </x-card>
    @endif

    @if ($step === 'map')
        <x-card class="border border-base-300">
            <p class="text-sm mb-3">
                @if ($mapHasHeader)
                    Map the CSV's columns to the fields we need. Detected headers: {{ implode(', ', $detectedHeaders) }}
                @else
                    No header row — pick columns by position. First-row sample: {{ implode(', ', $detectedHeaders) }}
                @endif
            </p>
            <x-checkbox label="First row is a header" wire:model.live="mapHasHeader" class="mb-3" />
            <div class="grid gap-3 md:grid-cols-2">
                <x-select label="Date column" :options="$columnOptions" placeholder="…" wire:model="mapDateColumn" />
                <x-input label="Date format" wire:model="mapDateFormat" hint="e.g. m/d/Y, d/m/Y, Y-m-d" />
                <x-select label="Description column" :options="$columnOptions" placeholder="…" wire:model="mapDescriptionColumn" />
                <x-select label="Amount column" :options="$columnOptions" placeholder="…" wire:model="mapAmountColumn" />
            </div>
            <div class="flex justify-end mt-4">
                <x-button label="Next" class="btn-primary" wire:click="proceedFromMap" />
            </div>
        </x-card>
    @endif

    @if ($step === 'preview')
        @include('livewire.imports.partials.preview')
    @endif

    @if ($step === 'done')
        <x-card class="border border-base-300">
            <div class="text-center space-y-3">
                <x-icon name="lucide.check-circle" class="size-12 mx-auto text-success" />
                <h2 class="text-lg font-semibold">Import complete</h2>
                <div class="flex gap-2 justify-center">
                    <x-button label="View import" link="{{ route('imports.index') }}" class="btn-primary" />
                    <x-button label="New import" link="{{ route('imports.new') }}" class="btn-ghost" />
                </div>
            </div>
        </x-card>
    @endif
</div>
