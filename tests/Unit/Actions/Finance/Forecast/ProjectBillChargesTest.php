<?php

use App\Actions\Finance\Forecast\ProjectBillCharges;
use App\Models\Account;
use App\Models\Bill;
use Carbon\CarbonImmutable;

it('emits monthly bills on their due day across range', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'due_day_of_month' => 15,
        'expected_amount_cents' => 150000,
    ]);

    $result = (new ProjectBillCharges)([$bill], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-09-30'));

    expect(array_column($result, 'date'))->toBe(['2026-07-15', '2026-08-15', '2026-09-15']);
    expect($result[0])->toMatchArray(['cents' => 150000, 'account_id' => $account->id, 'bill_id' => $bill->id]);
});

it('emits annual bills only in their month', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->annual()->create([
        'account_id' => $account->id,
        'due_day_of_month' => 10,
        'due_month_of_year' => 8,
    ]);

    $result = (new ProjectBillCharges)([$bill], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2027-09-30'));

    expect(array_column($result, 'date'))->toBe(['2026-08-10', '2027-08-10']);
});

it('clamps day-of-month for short months', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'due_day_of_month' => 31,
    ]);

    $result = (new ProjectBillCharges)([$bill], CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-04-30'));

    expect(array_column($result, 'date'))->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30']);
});

it('includes all bills regardless of paid status', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'due_day_of_month' => 5,
        'manually_marked_paid_periods' => CarbonImmutable::today()->format('Y-m'),
    ]);

    $result = (new ProjectBillCharges)([$bill], CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth());

    expect($result)->toHaveCount(1); // paid status does NOT remove the bill from the projection
});

it('skips bills with null account_id', function () {
    $bill = Bill::factory()->create(['account_id' => null]);

    $result = (new ProjectBillCharges)([$bill], CarbonImmutable::today()->startOfMonth(), CarbonImmutable::today()->endOfMonth());

    expect($result)->toBe([]);
});
