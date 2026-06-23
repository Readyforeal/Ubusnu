<?php

use App\Actions\Finance\Analytics\TopMovers;
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

it('returns categories ranked by largest absolute MoM delta', function () {
    $account = Account::factory()->create();
    $food = Category::factory()->create(['name' => 'Food', 'kind' => 'spending']);
    $gas = Category::factory()->create(['name' => 'Gas', 'kind' => 'spending']);

    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $food->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -10000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $gas->id, 'occurred_on' => '2026-06-12', 'amount_cents' => -10000]);

    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $food->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -20000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $gas->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -10000]);

    $result = (new TopMovers)(monthsBack: 1, limit: 5);

    expect($result[0]['name'])->toBe('Food');
    expect($result[0]['delta_pct'])->toBe(100.0);
    expect($result[0]['direction'])->toBe('up');
    expect($result[0]['current_cents'])->toBe(20000);
    expect($result[0]['previous_cents'])->toBe(10000);
});

it('marks new categories (no previous spend) with delta_pct=null', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -5000]);

    $result = (new TopMovers)();

    expect($result[0]['previous_cents'])->toBe(0);
    expect($result[0]['delta_pct'])->toBeNull();
    expect($result[0]['is_new_category'])->toBeTrue();
    expect($result[0]['direction'])->toBe('up');
});

it('caps the result at the given limit', function () {
    $account = Account::factory()->create();
    for ($i = 0; $i < 10; $i++) {
        $cat = Category::factory()->create(['kind' => 'spending']);
        Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -($i + 1) * 1000]);
    }

    $result = (new TopMovers)(limit: 3);

    expect($result)->toHaveCount(3);
});

it('ignores income-kind categories', function () {
    $account = Account::factory()->create();
    $income = Category::factory()->create(['kind' => 'income']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $income->id, 'occurred_on' => '2026-07-05', 'amount_cents' => 250000]);

    $result = (new TopMovers)();

    expect($result)->toBe([]);
});

it('returns empty when no transactions exist', function () {
    expect((new TopMovers)())->toBe([]);
});

it('marks downward movers with direction=down', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['kind' => 'spending']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -20000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -10000]);

    $result = (new TopMovers)();

    expect($result[0]['direction'])->toBe('down');
    expect($result[0]['delta_pct'])->toBe(-50.0);
});
