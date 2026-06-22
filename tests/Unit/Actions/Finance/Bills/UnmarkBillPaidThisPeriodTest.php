<?php

use App\Actions\Finance\Bills\UnmarkBillPaidThisPeriod;
use App\Models\Bill;
use Carbon\CarbonImmutable;

beforeEach(fn () => CarbonImmutable::setTestNow('2026-06-15'));
afterEach(fn () => CarbonImmutable::setTestNow());

it('removes the current period from manually_marked_paid_periods', function () {
    $bill = Bill::factory()->create([
        'cadence' => 'monthly',
        'manually_marked_paid_periods' => '2026-05,2026-06,2026-07',
    ]);

    (new UnmarkBillPaidThisPeriod)($bill);

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-05', '2026-07']);
});

it('is a no-op when the current period is not in the list', function () {
    $bill = Bill::factory()->create([
        'cadence' => 'monthly',
        'manually_marked_paid_periods' => '2026-05',
    ]);

    (new UnmarkBillPaidThisPeriod)($bill);

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-05']);
});
