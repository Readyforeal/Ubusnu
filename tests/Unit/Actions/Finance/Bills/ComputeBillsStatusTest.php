<?php

use App\Actions\Finance\Bills\ComputeBillsStatus;
use App\Models\Bill;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

beforeEach(function () {
    CarbonImmutable::setTestNow('2026-06-15');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
});

it('returns empty bills array and zero totals when no bills exist', function () {
    $result = (new ComputeBillsStatus)();

    expect($result['bills'])->toBe([]);
    expect($result['total_upcoming_cents'])->toBe(0);
    expect($result['total_paid_this_period_cents'])->toBe(0);
});

it('reports a bill with a matched transaction this period as paid via transaction', function () {
    $bill = Bill::factory()->create([
        'name' => 'Mortgage',
        'cadence' => 'monthly',
        'due_day_of_month' => 1,
        'expected_amount_cents' => 230000,
    ]);
    $tx = Transaction::factory()->create([
        'bill_id' => $bill->id,
        'amount_cents' => -230000,
        'occurred_on' => '2026-06-01',
    ]);

    $result = (new ComputeBillsStatus)();

    expect($result['bills'][0]['id'])->toBe($bill->id);
    expect($result['bills'][0]['is_paid_this_period'])->toBeTrue();
    expect($result['bills'][0]['payment_source'])->toBe('transaction');
    expect($result['bills'][0]['last_paid_transaction_id'])->toBe($tx->id);
});

it('reports a bill marked manually paid as paid via manual', function () {
    $bill = Bill::factory()->create([
        'cadence' => 'monthly',
        'due_day_of_month' => 1,
        'manually_marked_paid_periods' => '2026-06',
    ]);

    $result = (new ComputeBillsStatus)();

    expect($result['bills'][0]['is_paid_this_period'])->toBeTrue();
    expect($result['bills'][0]['payment_source'])->toBe('manual');
    expect($result['bills'][0]['last_paid_transaction_id'])->toBeNull();
});

it('reports an unpaid bill as unpaid', function () {
    Bill::factory()->create([
        'cadence' => 'monthly',
        'due_day_of_month' => 25,
        'expected_amount_cents' => 50000,
    ]);

    $result = (new ComputeBillsStatus)();

    expect($result['bills'][0]['is_paid_this_period'])->toBeFalse();
    expect($result['bills'][0]['payment_source'])->toBeNull();
});

it('excludes soft-deleted transactions from the paid check', function () {
    $bill = Bill::factory()->create([
        'cadence' => 'monthly',
        'due_day_of_month' => 1,
    ]);
    $tx = Transaction::factory()->create([
        'bill_id' => $bill->id,
        'occurred_on' => '2026-06-01',
    ]);
    $tx->delete();

    $result = (new ComputeBillsStatus)();

    expect($result['bills'][0]['is_paid_this_period'])->toBeFalse();
});

it('returns next_due_date and days_until_due correctly', function () {
    Bill::factory()->create(['cadence' => 'monthly', 'due_day_of_month' => 20]);

    $result = (new ComputeBillsStatus)();

    expect($result['bills'][0]['next_due_date'])->toBe('2026-06-20');
    expect($result['bills'][0]['days_until_due'])->toBe(5);
});

it('reports days_until_due for a bill whose due_day has already passed this month', function () {
    Bill::factory()->create(['cadence' => 'monthly', 'due_day_of_month' => 10]);

    $result = (new ComputeBillsStatus)();

    expect($result['bills'][0]['next_due_date'])->toBe('2026-07-10');
});

it('aggregates total_upcoming_cents excluding paid bills', function () {
    Bill::factory()->create([
        'name' => 'Paid Bill',
        'cadence' => 'monthly',
        'due_day_of_month' => 1,
        'expected_amount_cents' => 100000,
        'manually_marked_paid_periods' => '2026-06',
    ]);
    Bill::factory()->create([
        'name' => 'Unpaid Bill',
        'cadence' => 'monthly',
        'due_day_of_month' => 25,
        'expected_amount_cents' => 50000,
    ]);

    $result = (new ComputeBillsStatus)();

    expect($result['total_upcoming_cents'])->toBe(50000);
    expect($result['total_paid_this_period_cents'])->toBe(100000);
});

it('orders bills by sort_order then id', function () {
    Bill::factory()->create(['name' => 'B', 'sort_order' => 2]);
    Bill::factory()->create(['name' => 'A', 'sort_order' => 1]);
    Bill::factory()->create(['name' => 'C', 'sort_order' => 3]);

    $result = (new ComputeBillsStatus)();

    expect(array_map(fn ($b) => $b['name'], $result['bills']))->toBe(['A', 'B', 'C']);
});
