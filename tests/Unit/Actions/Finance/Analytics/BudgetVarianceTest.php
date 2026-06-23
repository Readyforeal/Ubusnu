<?php

use App\Actions\Finance\Analytics\BudgetVariance;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15');
    AppSetting::current()->update(['monthly_income_target_cents' => 480000]);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns each bucket with planned and actual cents', function () {
    $wants = Bucket::factory()->create(['name' => 'Wants', 'target_percentage' => 20]);
    $essentials = Bucket::factory()->create(['name' => 'Essentials', 'target_percentage' => 50]);

    $cat = Category::factory()->create(['kind' => 'spending', 'bucket_id' => $wants->id]);
    $account = Account::factory()->create();
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -50000]);

    $result = (new BudgetVariance)();

    $byBucket = collect($result)->keyBy('bucket_id');
    expect($byBucket[$wants->id]['planned_cents'])->toBe(96000);
    expect($byBucket[$wants->id]['actual_cents'])->toBe(50000);
    expect($byBucket[$essentials->id]['actual_cents'])->toBe(0);
});

it('returns days_remaining_in_period', function () {
    Bucket::factory()->create(['target_percentage' => 50]);

    $result = (new BudgetVariance)();

    expect($result[0]['days_remaining_in_period'])->toBe(17);
});

it('returns variance_pct as actual/planned * 100', function () {
    $b = Bucket::factory()->create(['target_percentage' => 50]);
    $cat = Category::factory()->create(['kind' => 'spending', 'bucket_id' => $b->id]);
    $account = Account::factory()->create();
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -120000]);

    $result = (new BudgetVariance)();

    expect((float) $result[0]['variance_pct'])->toBe(50.0);
});

it('handles a zero-planned bucket without divide-by-zero', function () {
    Bucket::factory()->create(['target_percentage' => 0]);

    $result = (new BudgetVariance)();

    expect($result[0]['variance_pct'])->toBe(0.0);
});
