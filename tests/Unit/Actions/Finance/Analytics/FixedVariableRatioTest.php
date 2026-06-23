<?php

use App\Actions\Finance\Analytics\FixedVariableRatio;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('classifies bill-linked transactions as fixed and others as variable', function () {
    $account = Account::factory()->create();
    $billCat = Category::factory()->create(['kind' => 'spending']);
    Bill::factory()->create(['category_id' => $billCat->id, 'account_id' => $account->id]);
    $varCat = Category::factory()->create(['kind' => 'spending']);

    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $billCat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -100000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $varCat->id, 'occurred_on' => '2026-07-10', 'amount_cents' => -50000]);

    $result = (new FixedVariableRatio)(monthsBack: 1);

    $july = collect($result)->firstWhere('month', '2026-07');
    expect($july['fixed_cents'])->toBe(100000);
    expect($july['variable_cents'])->toBe(50000);
    expect($july['fixed_ratio_pct'])->toBeGreaterThan(60.0);
});

it('returns one entry per month going back N months', function () {
    expect((new FixedVariableRatio)(monthsBack: 4))->toHaveCount(4);
});

it('handles months with no spending', function () {
    $result = (new FixedVariableRatio)(monthsBack: 1);

    expect($result[0]['fixed_ratio_pct'])->toBe(0.0);
});
