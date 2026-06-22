<?php

use App\Actions\Finance\Bills\MarkBillPaidThisPeriod;
use App\Models\Bill;
use Carbon\CarbonImmutable;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-06-15'));
afterEach(fn () => CarbonImmutable::setTestNow());

it('appends the current period to manually_marked_paid_periods for a monthly bill', function () {
    $bill = Bill::factory()->create(['cadence' => 'monthly']);

    (new MarkBillPaidThisPeriod)($bill);

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-06']);
});

it('appends the current year for an annual bill', function () {
    $bill = Bill::factory()->annual()->create();

    (new MarkBillPaidThisPeriod)($bill);

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026']);
});

it('is idempotent', function () {
    $bill = Bill::factory()->create(['cadence' => 'monthly']);

    (new MarkBillPaidThisPeriod)($bill);
    (new MarkBillPaidThisPeriod)($bill);

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-06']);
});
