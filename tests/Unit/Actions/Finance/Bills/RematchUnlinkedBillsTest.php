<?php

use App\Actions\Finance\Bills\RematchUnlinkedBills;
use App\Models\Bill;
use App\Models\Transaction;

it('links transactions with null bill_id whose descriptions match exactly one bill', function () {
    $bill = Bill::factory()->create(['match_description' => 'COMCAST']);
    Transaction::factory()->create(['description' => 'COMCAST XFINITY 8001234', 'bill_id' => null]);
    Transaction::factory()->create(['description' => 'STARBUCKS COFFEE', 'bill_id' => null]);

    $result = (new RematchUnlinkedBills)();

    expect($result)->toBe(['updated' => 1, 'still_unlinked' => 1]);
    expect(Transaction::where('description', 'COMCAST XFINITY 8001234')->first()->bill_id)->toBe($bill->id);
});

it('never overrides an already-linked transaction', function () {
    $bill = Bill::factory()->create(['match_description' => 'COMCAST']);
    $other = Bill::factory()->create();
    $tx = Transaction::factory()->create([
        'description' => 'COMCAST XFINITY',
        'bill_id' => $other->id,
    ]);

    (new RematchUnlinkedBills)();

    expect($tx->fresh()->bill_id)->toBe($other->id);
});

it('skips soft-deleted transactions', function () {
    $bill = Bill::factory()->create(['match_description' => 'COMCAST']);
    $tx = Transaction::factory()->create(['description' => 'COMCAST', 'bill_id' => null]);
    $tx->delete();

    $result = (new RematchUnlinkedBills)();

    expect($result['updated'])->toBe(0);
});

it('leaves bill_id null when match is ambiguous', function () {
    Bill::factory()->create(['match_description' => 'PAYMENT']);
    Bill::factory()->create(['match_description' => 'CARD']);
    $tx = Transaction::factory()->create(['description' => 'CARD PAYMENT', 'bill_id' => null]);

    (new RematchUnlinkedBills)();

    expect($tx->fresh()->bill_id)->toBeNull();
});

it('returns counts even when nothing changes', function () {
    Transaction::factory()->count(2)->create(['bill_id' => null]);

    $result = (new RematchUnlinkedBills)();

    expect($result)->toBe(['updated' => 0, 'still_unlinked' => 2]);
});
