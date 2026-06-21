<?php

use App\Actions\Finance\Imports\UndoImportBatch;
use App\Models\ImportBatch;
use App\Models\Transaction;

it('soft-deletes all transactions in the batch', function () {
    $batch = ImportBatch::factory()->create();
    Transaction::factory()->count(3)->create(['import_batch_id' => $batch->id]);

    (new UndoImportBatch)($batch);

    expect(Transaction::where('import_batch_id', $batch->id)->count())->toBe(0);
    expect(Transaction::withTrashed()->where('import_batch_id', $batch->id)->count())->toBe(3);
});

it('marks the batch as undone', function () {
    $batch = ImportBatch::factory()->create();

    (new UndoImportBatch)($batch);

    expect($batch->fresh()->isUndone())->toBeTrue();
});

it('does not touch transactions from other batches', function () {
    $batchA = ImportBatch::factory()->create();
    $batchB = ImportBatch::factory()->create();
    Transaction::factory()->count(2)->create(['import_batch_id' => $batchA->id]);
    Transaction::factory()->count(2)->create(['import_batch_id' => $batchB->id]);

    (new UndoImportBatch)($batchA);

    expect(Transaction::where('import_batch_id', $batchB->id)->count())->toBe(2);
});

it('is a no-op if batch is already undone', function () {
    $batch = ImportBatch::factory()->undone()->create();
    $originalTimestamp = $batch->undone_at;

    (new UndoImportBatch)($batch);

    expect($batch->fresh()->undone_at->equalTo($originalTimestamp))->toBeTrue();
});
