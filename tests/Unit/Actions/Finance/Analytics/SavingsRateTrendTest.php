<?php

use App\Actions\Finance\Analytics\SavingsRateTrend;
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

it('computes savings rate per month as (income - spend) / income', function () {
    $account = Account::factory()->create();
    $income = Category::factory()->create(['kind' => 'income']);
    $spend = Category::factory()->create(['kind' => 'spending']);

    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $income->id, 'occurred_on' => '2026-07-05', 'amount_cents' => 400000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $spend->id, 'occurred_on' => '2026-07-10', 'amount_cents' => -300000]);

    $result = (new SavingsRateTrend)(monthsBack: 1);
    $july = collect($result)->firstWhere('month', '2026-07');

    expect($july['income_cents'])->toBe(400000);
    expect($july['spend_cents'])->toBe(300000);
    expect($july['savings_rate_pct'])->toBe(25.0);
});

it('returns one entry per month going back N months', function () {
    $result = (new SavingsRateTrend)(monthsBack: 6);

    expect($result)->toHaveCount(6);
});

it('uses 0 for savings_rate_pct when income is zero', function () {
    $result = (new SavingsRateTrend)(monthsBack: 1);

    expect($result[0]['savings_rate_pct'])->toBe(0.0);
});
