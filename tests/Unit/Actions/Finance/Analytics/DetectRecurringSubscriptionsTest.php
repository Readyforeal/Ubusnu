<?php

use App\Actions\Finance\Analytics\DetectRecurringSubscriptions;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('finds repeating same-amount transactions not already tracked as bills', function () {
    $account = Account::factory()->create();
    for ($i = 0; $i < 3; $i++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'occurred_on' => CarbonImmutable::today()->subMonthsNoOverflow($i + 1)->toDateString(),
            'amount_cents' => -1599,
            'description' => 'NETFLIX.COM',
        ]);
    }

    $result = (new DetectRecurringSubscriptions)();

    expect($result)->toHaveCount(1);
    expect($result[0]['merchant_pattern'])->toContain('NETFLIX');
    expect($result[0]['occurrence_count'])->toBe(3);
    expect($result[0]['already_tracked_as_bill_id'])->toBeNull();
});

it('flags a subscription as already_tracked when a bill matches the description', function () {
    $account = Account::factory()->create();
    $bill = Bill::factory()->create(['match_description' => 'NETFLIX', 'account_id' => $account->id]);
    for ($i = 0; $i < 3; $i++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'occurred_on' => CarbonImmutable::today()->subMonthsNoOverflow($i + 1)->toDateString(),
            'amount_cents' => -1599,
            'description' => 'NETFLIX.COM',
        ]);
    }

    $result = (new DetectRecurringSubscriptions)();

    expect($result[0]['already_tracked_as_bill_id'])->toBe($bill->id);
});

it('ignores merchants with fewer than 3 occurrences', function () {
    $account = Account::factory()->create();
    for ($i = 0; $i < 2; $i++) {
        Transaction::factory()->create([
            'account_id' => $account->id,
            'occurred_on' => CarbonImmutable::today()->subMonthsNoOverflow($i + 1)->toDateString(),
            'amount_cents' => -999,
            'description' => 'SPOTIFY',
        ]);
    }

    expect((new DetectRecurringSubscriptions)())->toBe([]);
});
