<?php

use App\Actions\Finance\Transactions\DeleteTransaction;
use App\Models\Transaction;

it('soft-deletes the transaction', function () {
    $tx = Transaction::factory()->create();

    (new DeleteTransaction)($tx);

    expect(Transaction::find($tx->id))->toBeNull();
    expect(Transaction::withTrashed()->find($tx->id))->not->toBeNull();
});
