<?php

use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;

it('belongs to an account and a user', function () {
    $batch = ImportBatch::factory()->create();
    expect($batch->account)->toBeInstanceOf(Account::class);
    expect($batch->user)->toBeInstanceOf(User::class);
});

it('has many transactions', function () {
    $batch = ImportBatch::factory()->create();
    Transaction::factory()->count(3)->create(['import_batch_id' => $batch->id]);

    expect($batch->transactions)->toHaveCount(3);
});

it('scopes active to non-undone batches', function () {
    ImportBatch::factory()->count(2)->create();
    ImportBatch::factory()->undone()->create();

    expect(ImportBatch::active()->count())->toBe(2);
});

it('reports undone status', function () {
    expect(ImportBatch::factory()->undone()->create()->isUndone())->toBeTrue();
    expect(ImportBatch::factory()->create()->isUndone())->toBeFalse();
});
