<?php

use App\Actions\Finance\Bills\CreateBill;
use App\Models\Bill;

it('creates a bill with the given attributes', function () {
    $bill = (new CreateBill)([
        'name' => 'Mortgage',
        'cadence' => 'monthly',
        'due_day_of_month' => 1,
        'expected_amount_cents' => 230000,
        'match_description' => 'US BANK HOME MTG',
    ]);

    expect($bill)->toBeInstanceOf(Bill::class);
    expect($bill->name)->toBe('Mortgage');
    expect($bill->cadence)->toBe('monthly');
    expect($bill->due_day_of_month)->toBe(1);
    expect($bill->expected_amount_cents)->toBe(230000);
    expect($bill->match_description)->toBe('US BANK HOME MTG');
});

it('accepts optional nullable fields', function () {
    $bill = (new CreateBill)([
        'name' => 'Quick Bill',
        'cadence' => 'monthly',
        'due_day_of_month' => 15,
        'expected_amount_cents' => 5000,
    ]);

    expect($bill->account_id)->toBeNull();
    expect($bill->category_id)->toBeNull();
    expect($bill->match_description)->toBeNull();
});
