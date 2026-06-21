<?php

namespace App\Livewire\Imports;

use App\Actions\Finance\Imports\ImportTransactions;
use App\Actions\Finance\Imports\ParseCsvForPreview;
use App\Models\Account;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Import CSV')]
class Wizard extends Component
{
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

        $this->uploadedPath = $this->upload->getRealPath();
        $this->uploadedFilename = $this->upload->getClientOriginalName();
        $this->detectedHeaders = $this->sniffHeaders($this->uploadedPath);

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

    public function commit(): void
    {
        $account = Account::findOrFail($this->accountId);
        $batch = (new ImportTransactions)($account, $this->previewRows, auth()->id(), $this->uploadedFilename);
        $this->createdBatchId = $batch->id;
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

    public function render()
    {
        return view('livewire.imports.wizard', [
            'accounts' => Account::active()->orderBy('name')->get(),
            'columnOptions' => $this->columnOptions(),
            'counts' => [
                'new' => collect($this->previewRows)->where('status', 'new')->count(),
                'duplicate' => collect($this->previewRows)->where('status', 'duplicate')->count(),
                'error' => collect($this->previewRows)->where('status', 'error')->count(),
            ],
        ]);
    }
}
