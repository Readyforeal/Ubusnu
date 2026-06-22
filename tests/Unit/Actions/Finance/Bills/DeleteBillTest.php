<?php

use App\Actions\Finance\Bills\DeleteBill;
use App\Models\Bill;
use App\Models\Transaction;

it('deletes the bill', function () {
    $bill = Bill::factory()->create();

    (new DeleteBill)($bill);

    expect(Bill::find($bill->id))->toBeNull();
});

it('nullOnDelete unlinks linked transactions but keeps them', function () {
    $bill = Bill::factory()->create();
    $tx = Transaction::factory()->create(['bill_id' => $bill->id]);

    (new DeleteBill)($bill);

    expect(Transaction::find($tx->id))->not->toBeNull();
    expect($tx->fresh()->bill_id)->toBeNull();
});
