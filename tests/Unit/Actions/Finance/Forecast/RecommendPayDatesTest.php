<?php

use App\Actions\Finance\Forecast\ComputeProjectedBalance;
use App\Actions\Finance\Forecast\RecommendPayDates;
use App\Models\Account;
use App\Models\Bill;
use Carbon\CarbonImmutable;

function curve(int $accountId, CarbonImmutable $start, int $days, callable $balanceForDay): array
{
    $out = [];
    for ($i = 0; $i <= $days; $i++) {
        $date = $start->addDays($i)->toDateString();
        $out[] = ['account_id' => $accountId, 'date' => $date, 'balance_cents' => $balanceForDay($i)];
    }

    return $out;
}

it('recommends today when balance stays safe through due date', function () {
    $today = CarbonImmutable::today();
    $acct = Account::factory()->create(['minimum_balance_cents' => 50000]);
    $bill = Bill::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(10)->day,
        'expected_amount_cents' => 100000,
    ]);

    // Real-projection contract: bill already deducted at due date (day 10)
    $projection = curve($acct->id, $today, 10, fn ($d) => $d === 10 ? 400000 : 500000);

    $result = (new RecommendPayDates)([$bill], $projection, $today, $today->addDays(10));

    expect($result[0]['recommended_date'])->toBe($today->toDateString());
    expect($result[0]['warning'])->toBeFalse();
});

it('pushes recommendation to after a paycheck if today is unsafe', function () {
    $today = CarbonImmutable::today();
    $acct = Account::factory()->create(['minimum_balance_cents' => 50000]);
    $bill = Bill::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(10)->day,
        'expected_amount_cents' => 100000,
    ]);

    // Balance is too low until day 5, then jumps up; bill deducted at due date (day 10)
    $projection = curve($acct->id, $today, 10, fn ($d) => match (true) {
        $d < 5 => 120000,
        $d === 10 => 400000,
        default => 500000,
    });

    $result = (new RecommendPayDates)([$bill], $projection, $today, $today->addDays(10));

    expect($result[0]['recommended_date'])->toBe($today->addDays(5)->toDateString());
    expect($result[0]['warning'])->toBeFalse();
});

it('returns due_date with warning when no safe day exists', function () {
    $today = CarbonImmutable::today();
    $acct = Account::factory()->create(['minimum_balance_cents' => 50000]);
    $due = $today->addDays(5);
    $bill = Bill::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $due->day,
        'expected_amount_cents' => 100000,
    ]);

    // Real-projection contract: days 0-4 at 80000, day 5 = 80000-100000 = -20000 (bill deducted)
    $projection = curve($acct->id, $today, 5, fn ($d) => $d === 5 ? -20000 : 80000);

    $result = (new RecommendPayDates)([$bill], $projection, $today, $today->addDays(5));

    expect($result[0]['recommended_date'])->toBe($due->toDateString());
    expect($result[0]['warning'])->toBeTrue();
});

it('skips bills that have already been paid this period', function () {
    $today = CarbonImmutable::today();
    $acct = Account::factory()->create();
    $bill = Bill::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(5)->day,
        'manually_marked_paid_periods' => $today->format('Y-m'),
    ]);

    $projection = curve($acct->id, $today, 5, fn ($d) => 500000);

    $result = (new RecommendPayDates)([$bill], $projection, $today, $today->addDays(5));

    expect($result)->toBe([]);
});

it('does not falsely warn when balance stays above floor through due date', function () {
    $today = CarbonImmutable::today();
    $acct = Account::factory()->withStartingBalance(500000)->create(['minimum_balance_cents' => 300000]);
    $bill = Bill::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(5)->day,
        'expected_amount_cents' => 150000,
    ]);

    // Real projection: ComputeProjectedBalance already subtracts the bill on day 5
    $projection = (new ComputeProjectedBalance)([$acct->fresh()], $today, $today->addDays(5));

    $result = (new RecommendPayDates)([$bill], $projection, $today, $today->addDays(5));

    // 500k - 150k = 350k >= 300k floor → safe to pay today
    expect($result[0]['warning'])->toBeFalse();
    expect($result[0]['recommended_date'])->toBe($today->toDateString());
});

it('honors per-account minimum balance', function () {
    $today = CarbonImmutable::today();
    $low = Account::factory()->create(['minimum_balance_cents' => 0]);
    $high = Account::factory()->create(['minimum_balance_cents' => 200000]);

    $billLow = Bill::factory()->create([
        'account_id' => $low->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(5)->day,
        'expected_amount_cents' => 100000,
    ]);
    $billHigh = Bill::factory()->create([
        'account_id' => $high->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(5)->day,
        'expected_amount_cents' => 100000,
    ]);

    // Real-projection contract: bill deducted at due date (day 5) → 250000 - 100000 = 150000
    $projection = array_merge(
        curve($low->id, $today, 5, fn ($d) => $d === 5 ? 150000 : 250000),
        curve($high->id, $today, 5, fn ($d) => $d === 5 ? 150000 : 250000),
    );

    $result = (new RecommendPayDates)([$billLow, $billHigh], $projection, $today, $today->addDays(5));

    $byBill = [];
    foreach ($result as $row) {
        $byBill[$row['bill_id']] = $row;
    }

    // Low floor: today is safe (250k - 100k = 150k, ≥ 0)
    expect($byBill[$billLow->id]['recommended_date'])->toBe($today->toDateString());
    expect($byBill[$billLow->id]['warning'])->toBeFalse();

    // High floor: today not safe (250k - 100k = 150k, < 200k floor) → warning
    expect($byBill[$billHigh->id]['warning'])->toBeTrue();
});
