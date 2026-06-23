<?php

use App\Actions\Coach\BuildInsights;
use App\Coach\Insight;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns an array of Insight objects', function () {
    $result = (new BuildInsights)();
    expect($result)->toBeArray();
    foreach ($result as $item) {
        expect($item)->toBeInstanceOf(Insight::class);
    }
});

it('emits a warning when a top mover is up 100%+', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending', 'name' => 'Food']);

    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -10000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -25000]);

    $result = (new BuildInsights)();

    $foodInsight = collect($result)->first(fn (Insight $i) => str_contains($i->headline, 'Food'));
    expect($foodInsight)->not->toBeNull();
    expect($foodInsight->severity)->toBeIn(['warning', 'critical']);
});

it('caps the output at 6 insights', function () {
    AppSetting::current()->update(['monthly_income_target_cents' => 480000]);
    for ($i = 0; $i < 10; $i++) {
        Bucket::factory()->create(['name' => "B$i", 'target_percentage' => 5]);
    }
    $cats = Category::factory()->count(10)->create(['kind' => 'spending']);
    $account = Account::factory()->create();
    foreach ($cats as $cat) {
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -1000]);
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -5000]);
    }

    $result = (new BuildInsights)();

    expect(count($result))->toBeLessThanOrEqual(6);
});

it('ranks critical above warning above info above positive', function () {
    AppSetting::current()->update(['monthly_income_target_cents' => 480000]);
    $b = Bucket::factory()->create(['name' => 'Wants', 'target_percentage' => 20]);
    $cat = Category::factory()->create(['kind' => 'spending', 'bucket_id' => $b->id]);
    $account = Account::factory()->create();
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -100000]);

    $result = (new BuildInsights)();

    $order = ['critical' => 0, 'warning' => 1, 'info' => 2, 'positive' => 3];
    $severities = collect($result)->pluck('severity');
    $first = $severities->first();
    $last = $severities->last() ?? 'positive';
    expect($order[$first] ?? 3)->toBeLessThanOrEqual($order[$last] ?? 3);
});
