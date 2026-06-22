<?php

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;

it('persists all bill attributes', function () {
    $bill = Bill::factory()->create([
        'name' => 'US Bank Mortgage',
        'cadence' => 'monthly',
        'due_day_of_month' => 1,
        'expected_amount_cents' => 230000,
        'match_description' => 'US BANK HOME MTG',
    ]);

    expect($bill->name)->toBe('US Bank Mortgage');
    expect($bill->cadence)->toBe('monthly');
    expect($bill->due_day_of_month)->toBe(1);
    expect($bill->expected_amount_cents)->toBe(230000);
    expect($bill->match_description)->toBe('US BANK HOME MTG');
});

it('casts integer columns to int', function () {
    $bill = Bill::factory()->create([
        'due_day_of_month' => 15,
        'expected_amount_cents' => 50000,
        'sort_order' => 3,
    ]);

    expect($bill->due_day_of_month)->toBeInt();
    expect($bill->expected_amount_cents)->toBeInt();
    expect($bill->sort_order)->toBeInt();
});

it('allows null account, category, and match_description', function () {
    $bill = Bill::factory()->create([
        'account_id' => null,
        'category_id' => null,
        'match_description' => null,
    ]);

    expect($bill->account_id)->toBeNull();
    expect($bill->category_id)->toBeNull();
    expect($bill->match_description)->toBeNull();
});

it('belongsTo an account when set', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create(['account_id' => $account->id]);

    expect($bill->account->id)->toBe($account->id);
});

it('belongsTo a category when set', function () {
    $category = Category::factory()->create();
    $bill = Bill::factory()->create(['category_id' => $category->id]);

    expect($bill->category->id)->toBe($category->id);
});

it('hasMany transactions', function () {
    $bill = Bill::factory()->create();
    Transaction::factory()->count(2)->create(['bill_id' => $bill->id]);

    expect($bill->transactions)->toHaveCount(2);
});
