<?php

use App\Actions\Finance\Budgets\ComputeMonthlyBudgetStatus;
use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    AppSetting::current()->update(['monthly_income_target_cents' => 500000]);
});

it('returns the period and income target', function () {
    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['period'])->toBe('2026-06');
    expect($result['income_target_cents'])->toBe(500000);
});

it('computes income_actual_cents as the SUM of transactions in income-kind categories within the period', function () {
    $income = Category::factory()->incomeKind()->create();
    Transaction::factory()->create([
        'category_id' => $income->id,
        'amount_cents' => 250000,
        'occurred_on' => '2026-06-15',
    ]);
    Transaction::factory()->create([
        'category_id' => $income->id,
        'amount_cents' => 220000,
        'occurred_on' => '2026-06-30',
    ]);
    Transaction::factory()->create([
        'category_id' => $income->id,
        'amount_cents' => 100000,
        'occurred_on' => '2026-07-01',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['income_actual_cents'])->toBe(470000);
});

it('reports each bucket with target_cents and actual_cents (signed: + = net spent)', function () {
    $essentials = Bucket::factory()->create(['name' => 'Essentials', 'target_percentage' => 50, 'color' => '#22c55e']);
    $cat = Category::factory()->inBucket($essentials)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -182000,
        'occurred_on' => '2026-06-10',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'])->toHaveCount(1);
    expect($result['buckets'][0]['id'])->toBe($essentials->id);
    expect($result['buckets'][0]['name'])->toBe('Essentials');
    expect($result['buckets'][0]['color'])->toBe('#22c55e');
    expect($result['buckets'][0]['target_percentage'])->toBe(50);
    expect($result['buckets'][0]['target_cents'])->toBe(250000);
    expect($result['buckets'][0]['actual_cents'])->toBe(182000);
    expect($result['buckets'][0]['over_target'])->toBeFalse();
});

it('marks a bucket as over_target when actual exceeds target', function () {
    $tiny = Bucket::factory()->create(['target_percentage' => 10]);
    $cat = Category::factory()->inBucket($tiny)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -200000,
        'occurred_on' => '2026-06-05',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'][0]['target_cents'])->toBe(50000);
    expect($result['buckets'][0]['actual_cents'])->toBe(200000);
    expect($result['buckets'][0]['over_target'])->toBeTrue();
});

it('nets refunds against spending (positive amount in a spending category reduces actual_cents)', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 20]);
    $cat = Category::factory()->inBucket($bucket)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -5000,
        'occurred_on' => '2026-06-10',
    ]);
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => 8000,
        'occurred_on' => '2026-06-15',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'][0]['actual_cents'])->toBe(-3000);
});

it('excludes transfer-kind categories from buckets and income', function () {
    $transfer = Category::factory()->transferKind()->create();
    Transaction::factory()->create([
        'category_id' => $transfer->id,
        'amount_cents' => 100000,
        'occurred_on' => '2026-06-10',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['income_actual_cents'])->toBe(0);
    expect($result['buckets'])->toBeEmpty();
    expect($result['unassigned_actual_cents'])->toBe(0);
});

it('reports unassigned spending in unassigned_actual_cents', function () {
    $cat = Category::factory()->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -4500,
        'occurred_on' => '2026-06-10',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['unassigned_actual_cents'])->toBe(4500);
    expect($result['buckets'])->toBeEmpty();
});

it('excludes soft-deleted transactions', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);
    $cat = Category::factory()->inBucket($bucket)->create();
    $tx = Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -10000,
        'occurred_on' => '2026-06-10',
    ]);
    $tx->delete();

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'])->toHaveCount(1);
    expect($result['buckets'][0]['actual_cents'])->toBe(0);
});

it('excludes transactions outside the requested period', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);
    $cat = Category::factory()->inBucket($bucket)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -10000,
        'occurred_on' => '2026-05-31',
    ]);
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -10000,
        'occurred_on' => '2026-07-01',
    ]);

    $result = (new ComputeMonthlyBudgetStatus)('2026-06');

    expect($result['buckets'][0]['actual_cents'])->toBe(0);
});

it('defaults to current month when no period is supplied', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);
    $cat = Category::factory()->inBucket($bucket)->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -5000,
        'occurred_on' => CarbonImmutable::today()->toDateString(),
    ]);

    $result = (new ComputeMonthlyBudgetStatus)();

    expect($result['period'])->toBe(CarbonImmutable::today()->format('Y-m'));
    expect($result['buckets'][0]['actual_cents'])->toBe(5000);
});
