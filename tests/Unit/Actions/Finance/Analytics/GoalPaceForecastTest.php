<?php

use App\Actions\Finance\Analytics\GoalPaceForecast;
use App\Models\Account;
use App\Models\Goal;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns one entry per goal with target and current cents', function () {
    $account = Account::factory()->withStartingBalance(100000)->create(['counts_toward_goals' => true]);
    $goal = Goal::factory()->create(['name' => 'Vacation', 'target_cents' => 500000, 'priority_percentage' => 100]);

    $result = (new GoalPaceForecast)();

    expect($result)->toHaveCount(1);
    expect($result[0]['goal_id'])->toBe($goal->id);
    expect($result[0]['target_cents'])->toBe(500000);
    expect($result[0]['current_cents'])->toBe(100000);
});

it('forecasts a projected_hit_date based on positive monthly pace', function () {
    $account = Account::factory()->withStartingBalance(0)->create(['counts_toward_goals' => true]);
    Goal::factory()->create(['target_cents' => 600000, 'priority_percentage' => 100]);

    // 3 months of $50k net deposits = $50k/mo pace; $600k target → 12 months out
    Transaction::factory()->create(['account_id' => $account->id, 'occurred_on' => '2026-05-15', 'amount_cents' => 50000]);
    Transaction::factory()->create(['account_id' => $account->id, 'occurred_on' => '2026-06-15', 'amount_cents' => 50000]);
    Transaction::factory()->create(['account_id' => $account->id, 'occurred_on' => '2026-07-10', 'amount_cents' => 50000]);

    $result = (new GoalPaceForecast)();

    expect($result[0]['monthly_pace_cents'])->toBeGreaterThan(0);
    expect($result[0]['projected_hit_date'])->not->toBeNull();
});

it('returns null projected_hit_date when monthly pace is zero', function () {
    Account::factory()->withStartingBalance(0)->create(['counts_toward_goals' => true]);
    Goal::factory()->create(['target_cents' => 600000, 'priority_percentage' => 100]);

    $result = (new GoalPaceForecast)();

    expect($result[0]['monthly_pace_cents'])->toBe(0);
    expect($result[0]['projected_hit_date'])->toBeNull();
});

it('returns today as hit date when goal is already fully funded', function () {
    Account::factory()->withStartingBalance(1000000)->create(['counts_toward_goals' => true]);
    Goal::factory()->create(['target_cents' => 500000, 'priority_percentage' => 100]);

    $result = (new GoalPaceForecast)();

    expect($result[0]['projected_hit_date'])->toBe(CarbonImmutable::today()->toDateString());
});
