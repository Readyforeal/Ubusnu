<?php

use App\Actions\Finance\Analytics\SpendingVelocity;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('compares this month spend so-far against last month through the same day', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    for ($d = 1; $d <= 15; $d++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT), 'amount_cents' => -6667]);
    }
    for ($d = 1; $d <= 15; $d++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT), 'amount_cents' => -13333]);
    }

    $result = (new SpendingVelocity)();

    expect($result['this_month_cents_so_far'])->toBeGreaterThan($result['last_month_cents_through_same_day']);
    expect($result['delta_pct'])->toBeGreaterThan(0);
});

it('projects the full month based on this-month run-rate', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    for ($d = 1; $d <= 15; $d++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-'.str_pad((string) $d, 2, '0', STR_PAD_LEFT), 'amount_cents' => -10000]);
    }

    $result = (new SpendingVelocity)();

    expect($result['projected_full_month_cents'])->toBeGreaterThan($result['this_month_cents_so_far']);
});

it('returns zero values when there are no transactions', function () {
    $result = (new SpendingVelocity)();

    expect($result['this_month_cents_so_far'])->toBe(0);
    expect($result['last_month_cents_through_same_day'])->toBe(0);
    expect($result['delta_pct'])->toBe(0.0);
});
