<?php

namespace App\Livewire\Imports;

use App\Actions\Finance\Imports\UndoImportBatch;
use App\Models\ImportBatch;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Imports')]
class Index extends Component
{
    public bool $showUndone = false;

    public function undo(int $batchId): void
    {
        $batch = ImportBatch::findOrFail($batchId);
        (new UndoImportBatch)($batch);
    }

    #[Computed]
    public function batches(): Collection
    {
        return ImportBatch::query()
            ->with(['account', 'user'])
            ->when(! $this->showUndone, fn ($q) => $q->whereNull('undone_at'))
            ->when($this->showUndone, fn ($q) => $q->whereNotNull('undone_at'))
            ->orderByDesc('created_at')
            ->get();
    }

    public function render()
    {
        return view('livewire.imports.index', ['batches' => $this->batches]);
    }
}
