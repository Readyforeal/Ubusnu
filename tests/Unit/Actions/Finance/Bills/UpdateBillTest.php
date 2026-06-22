<?php

use App\Actions\Finance\Bills\UpdateBill;
use App\Models\Bill;

it('updates allowed attributes', function () {
    $bill = Bill::factory()->create(['name' => 'Old', 'expected_amount_cents' => 1000]);

    (new UpdateBill)($bill, [
        'name' => 'New',
        'expected_amount_cents' => 2000,
        'match_description' => 'NEW MATCH',
    ]);

    $bill->refresh();
    expect($bill->name)->toBe('New');
    expect($bill->expected_amount_cents)->toBe(2000);
    expect($bill->match_description)->toBe('NEW MATCH');
});

it('ignores attributes outside the allowed list', function () {
    $bill = Bill::factory()->create();

    (new UpdateBill)($bill, ['id' => 999, 'name' => 'Renamed']);

    $bill->refresh();
    expect($bill->name)->toBe('Renamed');
    expect($bill->id)->not->toBe(999);
});
