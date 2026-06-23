<?php

use App\Actions\Finance\Analytics\DetectAnomalies;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('flags transactions far from the category median', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);

    for ($i = 0; $i < 30; $i++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'category_id' => $cat->id,
            'occurred_on' => CarbonImmutable::today()->subDays($i + 1)->toDateString(),
            'amount_cents' => -(5000 + ($i % 5) * 100),
        ]);
    }

    $spike = Transaction::factory()->create([
        'account_id' => $account->id,
        'category_id' => $cat->id,
        'occurred_on' => CarbonImmutable::today()->toDateString(),
        'amount_cents' => -50000,
    ]);

    $result = (new DetectAnomalies)(lookbackDays: 90, stdDevThreshold: 2.0);

    expect($result)->toHaveCount(1);
    expect($result[0]['transaction_id'])->toBe($spike->id);
    expect($result[0]['std_devs_from_median'])->toBeGreaterThan(2.0);
});

it('returns empty for categories with too few transactions to compute std-dev', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->toDateString(), 'amount_cents' => -100000]);

    expect((new DetectAnomalies)())->toBe([]);
});

it('ignores income-kind categories', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'income']);
    for ($i = 0; $i < 30; $i++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->subDays($i + 1)->toDateString(), 'amount_cents' => 250000]);
    }
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->toDateString(), 'amount_cents' => 9999999]);

    expect((new DetectAnomalies)())->toBe([]);
});

it('respects the std-dev threshold', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);
    for ($i = 0; $i < 20; $i++) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->subDays($i + 1)->toDateString(), 'amount_cents' => -10000]);
    }
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => CarbonImmutable::today()->toDateString(), 'amount_cents' => -15000]);

    expect((new DetectAnomalies)(stdDevThreshold: 5.0))->toBe([]);
});
