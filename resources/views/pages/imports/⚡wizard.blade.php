<?php

use App\Actions\Finance\Imports\ImportTransactions;
use App\Actions\Finance\Imports\ParseCsvForPreview;
use App\Models\Account;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Import CSV')] class extends Component {
    use WithFileUploads;

    public string $step = 'upload';

    public ?int $accountId = null;

    public $upload = null;

    public string $uploadedPath = '';

    public string $uploadedFilename = '';

    /** @var array<int, string> */
    public array $detectedHeaders = [];

    public string $mapDateColumn = '';

    public string $mapDateFormat = 'm/d/Y';

    public string $mapDescriptionColumn = '';

    public string $mapAmountColumn = '';

    public bool $mapHasHeader = true;

    /** @var array<int, array<string, mixed>> */
    public array $previewRows = [];

    public ?int $createdBatchId = null;

    public function proceedFromUpload(): void
    {
        $this->validate([
            'accountId' => 'required|integer|exists:accounts,id',
            'upload' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $tempPath = $this->upload->getRealPath();
        $this->uploadedFilename = $this->upload->getClientOriginalName();
        $this->detectedHeaders = $this->sniffHeaders($tempPath);

        // Copy the upload to a path we own for the rest of the wizard, then
        // release the Livewire upload reference so it doesn't have to hydrate
        // on every subsequent request.
        $persistent = storage_path('app/imports/'.uniqid('wiz_').'.csv');
        @mkdir(dirname($persistent), 0755, true);
        copy($tempPath, $persistent);
        $this->uploadedPath = $persistent;
        $this->upload = null;

        $account = Account::findOrFail($this->accountId);
        $profile = $account->import_profile;

        if ($profile && $this->headersMatch($profile, $this->detectedHeaders)) {
            $this->applyProfile($profile);
            $this->buildPreview();
            $this->step = 'preview';

            return;
        }

        if ($profile) {
            $this->mapDateColumn = $profile['date_column'] ?? '';
            $this->mapDateFormat = $profile['date_format'] ?? 'm/d/Y';
            $this->mapDescriptionColumn = $profile['description_column'] ?? '';
            $this->mapAmountColumn = $profile['amount_column'] ?? '';
            $this->mapHasHeader = $profile['has_header'] ?? true;
        }

        $this->step = 'map';
    }

    public function proceedFromMap(): void
    {
        $this->validate([
            'mapDateColumn' => 'required|string',
            'mapDateFormat' => 'required|string',
            'mapDescriptionColumn' => 'required|string',
            'mapAmountColumn' => 'required|string',
        ]);

        $account = Account::findOrFail($this->accountId);
        $account->update([
            'import_profile' => [
                'delimiter' => ',',
                'has_header' => $this->mapHasHeader,
                'date_column' => $this->mapDateColumn,
                'date_format' => $this->mapDateFormat,
                'description_column' => $this->mapDescriptionColumn,
                'amount_column' => $this->mapAmountColumn,
            ],
        ]);

        $this->buildPreview();
        $this->step = 'preview';
    }

    public function runImport(): void
    {
        $account = Account::findOrFail($this->accountId);
        $batch = (new ImportTransactions)($account, $this->previewRows, auth()->id(), $this->uploadedFilename);
        $this->createdBatchId = $batch->id;

        if ($this->uploadedPath && file_exists($this->uploadedPath)) {
            @unlink($this->uploadedPath);
        }

        $this->step = 'done';
    }

    public function toggleRow(int $index): void
    {
        if (! isset($this->previewRows[$index])) {
            return;
        }
        $current = $this->previewRows[$index]['status'];
        if ($current === 'error') {
            return;
        }
        $this->previewRows[$index]['status'] = $current === 'duplicate' ? 'new' : 'duplicate';
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    public function columnOptions(): array
    {
        if ($this->mapHasHeader) {
            return collect($this->detectedHeaders)
                ->map(fn (string $h) => ['id' => $h, 'name' => $h])
                ->all();
        }

        return collect($this->detectedHeaders)
            ->map(fn (string $sample, int $i) => [
                'id' => (string) $i,
                'name' => 'Column '.($i + 1).' — '.$sample,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function sniffHeaders(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }
        $first = fgetcsv($handle);
        fclose($handle);

        return is_array($first) ? array_map('strval', $first) : [];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<int, string>  $headers
     */
    private function headersMatch(array $profile, array $headers): bool
    {
        return in_array($profile['date_column'] ?? null, $headers, true)
            && in_array($profile['description_column'] ?? null, $headers, true)
            && in_array($profile['amount_column'] ?? null, $headers, true);
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function applyProfile(array $profile): void
    {
        $this->mapDateColumn = $profile['date_column'];
        $this->mapDateFormat = $profile['date_format'];
        $this->mapDescriptionColumn = $profile['description_column'];
        $this->mapAmountColumn = $profile['amount_column'];
        $this->mapHasHeader = $profile['has_header'] ?? true;
    }

    private function buildPreview(): void
    {
        $account = Account::findOrFail($this->accountId);
        $this->previewRows = (new ParseCsvForPreview)($account, $this->uploadedPath, [
            'delimiter' => ',',
            'has_header' => $this->mapHasHeader,
            'date_column' => $this->mapDateColumn,
            'date_format' => $this->mapDateFormat,
            'description_column' => $this->mapDescriptionColumn,
            'amount_column' => $this->mapAmountColumn,
        ]);
    }

    public function with(): array
    {
        return [
            'accounts' => Account::active()->orderBy('name')->get(),
            'columnOptions' => $this->columnOptions(),
            'counts' => [
                'new' => collect($this->previewRows)->where('status', 'new')->count(),
                'duplicate' => collect($this->previewRows)->where('status', 'duplicate')->count(),
                'error' => collect($this->previewRows)->where('status', 'error')->count(),
            ],
        ];
    }
}; ?>

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
        <x-card class="border border-base-300">
            <div class="flex justify-between items-center mb-3 text-sm">
                <div class="space-x-3">
                    <span class="text-success">{{ $counts['new'] }} new</span>
                    <span class="text-warning">{{ $counts['duplicate'] }} duplicates</span>
                    <span class="text-error">{{ $counts['error'] }} errors</span>
                </div>
                <x-button label="Import {{ $counts['new'] }} rows" class="btn-primary" wire:click="runImport" wire:loading.attr="disabled" />
            </div>

            <div class="overflow-x-auto">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Date</th>
                            <th>Description</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($previewRows as $i => $row)
                            <tr wire:key="row-{{ $i }}" class="{{ $row['status'] === 'error' ? 'opacity-50' : '' }}">
                                <td>
                                    @if ($row['status'] !== 'error')
                                        <input type="checkbox"
                                               class="checkbox checkbox-sm"
                                               wire:click="toggleRow({{ $i }})"
                                               @checked($row['status'] === 'new') />
                                    @endif
                                </td>
                                <td>{{ $row['occurred_on'] ?? '—' }}</td>
                                <td>{{ $row['description'] ?? '—' }}</td>
                                <td class="text-right font-mono">
                                    @if ($row['amount_cents'] !== null)
                                        {{ \App\Support\Money::format($row['amount_cents']) }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if ($row['status'] === 'new')
                                        <x-badge value="New" class="badge-success badge-sm" />
                                    @elseif ($row['status'] === 'duplicate')
                                        <x-badge value="Duplicate" class="badge-warning badge-sm" />
                                    @else
                                        <x-badge value="Error" class="badge-error badge-sm" />
                                        <div class="text-xs text-error mt-1">{{ $row['error'] ?? '' }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    @if ($step === 'done')
        <x-card class="border border-base-300">
            <div class="text-center space-y-3">
                <x-icon name="lucide.check-circle" class="size-12 mx-auto text-success" />
                <h2 class="text-lg font-semibold">Import complete</h2>
                <div class="flex gap-2 justify-center">
                    @if ($createdBatchId)
                        <x-button label="View this import" link="{{ route('imports.show', $createdBatchId) }}" class="btn-primary" />
                    @endif
                    <x-button label="All imports" link="{{ route('imports.index') }}" class="btn-ghost" />
                    <x-button label="New import" link="{{ route('imports.new') }}" class="btn-ghost" />
                </div>
            </div>
        </x-card>
    @endif
</div>
