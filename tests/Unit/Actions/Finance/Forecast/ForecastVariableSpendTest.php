<?php

use App\Actions\Finance\Forecast\ForecastVariableSpend;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    AppSetting::current()->update(['forecast_lookback_weeks' => 12]);
});

it('forecasts daily spend as weekly median divided by seven', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    // 12 weeks of $70 weekly spend → median weekly = 70, per-day = 10
    for ($w = 0; $w < 12; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -7000,
        ]);
    }

    $start = CarbonImmutable::today();
    $end = $start->addDays(2);
    $result = (new ForecastVariableSpend)($start, $end);

    $byDate = [];
    foreach ($result as $row) {
        $byDate[$row['date']] = ($byDate[$row['date']] ?? 0) + $row['cents'];
    }

    expect($byDate[$start->toDateString()])->toBe(1000);
    expect($byDate[$start->addDay()->toDateString()])->toBe(1000);
    expect($byDate[$end->toDateString()])->toBe(1000);
});

it('uses median not mean (outliers do not dominate)', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    // 11 weeks of $70, 1 week of $5000. Mean is high; median should still be 70.
    for ($w = 0; $w < 11; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -7000,
        ]);
    }
    Transaction::factory()->create([
        'account_id' => $account->id,
        'category_id' => $cat->id,
        'occurred_on' => CarbonImmutable::today()->subWeek()->toDateString(),
        'amount_cents' => -500000,
    ]);

    $start = CarbonImmutable::today();
    $end = $start->addDay();
    $result = (new ForecastVariableSpend)($start, $end);

    $byDate = [];
    foreach ($result as $row) {
        $byDate[$row['date']] = ($byDate[$row['date']] ?? 0) + $row['cents'];
    }

    // Median weekly = 7000, /7 = 1000/day (well below mean which would be ~14k/day)
    expect($byDate[$start->toDateString()])->toBe(1000);
});

it('excludes categories that are tied to a bill', function () {
    $account = Account::factory()->create();
    $billCat = Category::factory()->create(['kind' => 'spending', 'name' => 'Mortgage Cat']);
    Bill::factory()->create(['category_id' => $billCat->id, 'account_id' => $account->id]);

    for ($w = 0; $w < 12; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $billCat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -150000,
        ]);
    }

    $result = (new ForecastVariableSpend)(CarbonImmutable::today(), CarbonImmutable::today()->addDay());

    expect($result)->toBe([]);
});

it('returns no forecast for categories with fewer than four weeks of data', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    for ($w = 0; $w < 3; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -10000,
        ]);
    }

    $result = (new ForecastVariableSpend)(CarbonImmutable::today(), CarbonImmutable::today()->addDay());

    expect($result)->toBe([]);
});

it('respects forecast_lookback_weeks setting', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    // Spend in the past 4 weeks only
    for ($w = 0; $w < 4; $w++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subWeeks($w + 1)->toDateString(),
            'amount_cents' => -7000,
        ]);
    }

    AppSetting::current()->update(['forecast_lookback_weeks' => 4]);

    $result = (new ForecastVariableSpend)(CarbonImmutable::today(), CarbonImmutable::today());

    expect($result)->not->toBe([]);

    AppSetting::current()->update(['forecast_lookback_weeks' => 2]);

    $result = (new ForecastVariableSpend)(CarbonImmutable::today(), CarbonImmutable::today());

    // Only 2 weeks of data falls within the 2-week lookback → below the 4-week threshold → empty
    expect($result)->toBe([]);
});
