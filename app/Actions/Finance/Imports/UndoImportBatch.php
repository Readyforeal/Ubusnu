<?php

namespace App\Actions\Finance\Imports;

use App\Models\ImportBatch;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class UndoImportBatch
{
    public function __invoke(ImportBatch $batch): ImportBatch
    {
        if ($batch->isUndone()) {
            return $batch;
        }

        DB::transaction(function () use ($batch) {
            Transaction::where('import_batch_id', $batch->id)->delete();
            $batch->update(['undone_at' => now()]);
        });

        return $batch->fresh();
    }
}
