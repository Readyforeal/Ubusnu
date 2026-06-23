<?php

use App\Actions\Finance\Income\AdvanceIncomeAnchor;
use App\Models\IncomeSource;

it('advances monthly cadence by one month', function () {
    $source = IncomeSource::factory()->create([
        'cadence' => 'monthly',
        'next_expected_on' => '2026-07-10',
    ]);

    (new AdvanceIncomeAnchor)($source);

    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-08-10');
});

it('advances weekly cadence by one week', function () {
    $source = IncomeSource::factory()->weekly()->create([
        'next_expected_on' => '2026-07-10',
    ]);

    (new AdvanceIncomeAnchor)($source);

    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-07-17');
});

it('advances biweekly cadence by two weeks', function () {
    $source = IncomeSource::factory()->biweekly()->create([
        'next_expected_on' => '2026-07-10',
    ]);

    (new AdvanceIncomeAnchor)($source);

    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-07-24');
});

it('alternates semi_monthly between primary and secondary days', function () {
    $source = IncomeSource::factory()->semiMonthly()->create([
        'next_expected_on' => '2026-07-01',
        'primary_day_of_month' => 1,
        'secondary_day_of_month' => 15,
    ]);

    (new AdvanceIncomeAnchor)($source);
    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-07-15');

    (new AdvanceIncomeAnchor)($source->fresh());
    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-08-01');
});

it('clamps to last-day-of-month when day exceeds month length', function () {
    $source = IncomeSource::factory()->create([
        'cadence' => 'monthly',
        'next_expected_on' => '2026-01-31',
        'primary_day_of_month' => 31,
    ]);

    (new AdvanceIncomeAnchor)($source);

    expect($source->fresh()->next_expected_on->toDateString())->toBe('2026-02-28');
});
