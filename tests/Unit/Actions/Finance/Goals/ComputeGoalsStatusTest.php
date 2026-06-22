<?php

use App\Actions\Finance\Goals\ComputeGoalsStatus;
use App\Models\Account;
use App\Models\Goal;
use App\Models\Transaction;

it('returns empty status when no goals and no savings accounts exist', function () {
    $result = (new ComputeGoalsStatus)();

    expect($result['pool_cents'])->toBe(0);
    expect($result['goals'])->toBe([]);
    expect($result['total_allocated_cents'])->toBe(0);
    expect($result['unallocated_cents'])->toBe(0);
    expect($result['total_priority_percentage'])->toBe(0);
});

it('computes pool from a single savings account starting balance and transactions', function () {
    $account = Account::factory()->withStartingBalance(500000)->countsTowardGoals()->create();
    Transaction::factory()->forAccount($account)->withAmount(200000)->create();
    Transaction::factory()->forAccount($account)->withAmount(-50000)->create();

    $result = (new ComputeGoalsStatus)();

    expect($result['pool_cents'])->toBe(650000);
});

it('sums pool across multiple savings accounts', function () {
    $a = Account::factory()->withStartingBalance(100000)->countsTowardGoals()->create();
    $b = Account::factory()->withStartingBalance(200000)->countsTowardGoals()->create();
    Transaction::factory()->forAccount($a)->withAmount(50000)->create();
    Transaction::factory()->forAccount($b)->withAmount(-30000)->create();

    $result = (new ComputeGoalsStatus)();

    expect($result['pool_cents'])->toBe(320000);
});

it('excludes archived savings accounts from the pool', function () {
    Account::factory()->withStartingBalance(100000)->countsTowardGoals()->create();
    Account::factory()->withStartingBalance(500000)->countsTowardGoals()->archived()->create();

    $result = (new ComputeGoalsStatus)();

    expect($result['pool_cents'])->toBe(100000);
});

it('excludes accounts not flagged counts_toward_goals from the pool', function () {
    Account::factory()->withStartingBalance(100000)->countsTowardGoals()->create();
    Account::factory()->withStartingBalance(500000)->create();

    $result = (new ComputeGoalsStatus)();

    expect($result['pool_cents'])->toBe(100000);
});

it('excludes soft-deleted transactions from the pool', function () {
    $account = Account::factory()->withStartingBalance(100000)->countsTowardGoals()->create();
    $tx = Transaction::factory()->forAccount($account)->withAmount(50000)->create();
    Transaction::factory()->forAccount($account)->withAmount(20000)->create();
    $tx->delete();

    $result = (new ComputeGoalsStatus)();

    expect($result['pool_cents'])->toBe(120000);
});

it('computes raw and capped allocation per goal', function () {
    Account::factory()->withStartingBalance(1000000)->countsTowardGoals()->create();
    Goal::factory()->create(['name' => 'Camera', 'target_cents' => 150000, 'priority_percentage' => 10]);

    $result = (new ComputeGoalsStatus)();

    expect($result['goals'][0]['raw_allocation_cents'])->toBe(100000);
    expect($result['goals'][0]['capped_allocation_cents'])->toBe(100000);
    expect($result['goals'][0]['overflow_cents'])->toBe(0);
});

it('caps allocation at target and tracks overflow when over-funded', function () {
    Account::factory()->withStartingBalance(1000000)->countsTowardGoals()->create();
    Goal::factory()->create(['name' => 'Debt', 'target_cents' => 200000, 'priority_percentage' => 30]);

    $result = (new ComputeGoalsStatus)();

    expect($result['goals'][0]['raw_allocation_cents'])->toBe(300000);
    expect($result['goals'][0]['capped_allocation_cents'])->toBe(200000);
    expect($result['goals'][0]['overflow_cents'])->toBe(100000);
    expect($result['goals'][0]['funded_percentage'])->toBe(100);
    expect($result['goals'][0]['is_fully_funded'])->toBeTrue();
});

it('reports under-funded goals with funded_percentage as integer-rounded', function () {
    Account::factory()->withStartingBalance(1000000)->countsTowardGoals()->create();
    Goal::factory()->create(['target_cents' => 150000, 'priority_percentage' => 10]);

    $result = (new ComputeGoalsStatus)();

    // raw = 100000, capped = 100000, funded% = 100000/150000*100 = 66 (intdiv)
    expect($result['goals'][0]['funded_percentage'])->toBe(66);
    expect($result['goals'][0]['is_fully_funded'])->toBeFalse();
});

it('returns unallocated as pool minus sum of capped allocations, including overflow from fully-funded goals', function () {
    Account::factory()->withStartingBalance(1000000)->countsTowardGoals()->create();
    Goal::factory()->create(['name' => 'Debt', 'target_cents' => 200000, 'priority_percentage' => 30]);
    Goal::factory()->create(['name' => 'Camera', 'target_cents' => 150000, 'priority_percentage' => 10]);
    Goal::factory()->create(['name' => 'Emergency', 'target_cents' => 1000000, 'priority_percentage' => 20]);

    $result = (new ComputeGoalsStatus)();

    expect($result['total_allocated_cents'])->toBe(500000);
    expect($result['unallocated_cents'])->toBe(500000);
});

it('reports total_priority_percentage as the sum of all goal priority %s', function () {
    Goal::factory()->create(['priority_percentage' => 30]);
    Goal::factory()->create(['priority_percentage' => 10]);
    Goal::factory()->create(['priority_percentage' => 20]);

    $result = (new ComputeGoalsStatus)();

    expect($result['total_priority_percentage'])->toBe(60);
});

it('returns all goals showing 0% funded when pool is 0', function () {
    Goal::factory()->create(['target_cents' => 100000, 'priority_percentage' => 50]);

    $result = (new ComputeGoalsStatus)();

    expect($result['pool_cents'])->toBe(0);
    expect($result['goals'][0]['raw_allocation_cents'])->toBe(0);
    expect($result['goals'][0]['capped_allocation_cents'])->toBe(0);
    expect($result['goals'][0]['funded_percentage'])->toBe(0);
    expect($result['goals'][0]['is_fully_funded'])->toBeFalse();
});

it('orders goals by sort_order then id', function () {
    Goal::factory()->create(['name' => 'B', 'sort_order' => 2]);
    Goal::factory()->create(['name' => 'A', 'sort_order' => 1]);
    Goal::factory()->create(['name' => 'C', 'sort_order' => 3]);

    $result = (new ComputeGoalsStatus)();

    expect(array_map(fn ($g) => $g['name'], $result['goals']))->toBe(['A', 'B', 'C']);
});
