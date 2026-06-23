<?php

use App\Actions\Finance\Forecast\ProjectIncomeDeposits;
use App\Models\Account;
use App\Models\IncomeSource;
use Carbon\CarbonImmutable;

it('emits one deposit per occurrence within range for monthly cadence', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'next_expected_on' => '2026-07-01',
        'expected_amount_cents' => 250000,
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-09-30'));

    expect($result)->toHaveCount(3);
    expect($result[0])->toMatchArray(['date' => '2026-07-01', 'account_id' => $account->id, 'cents' => 250000, 'income_source_id' => $source->id]);
    expect($result[1]['date'])->toBe('2026-08-01');
    expect($result[2]['date'])->toBe('2026-09-01');
});

it('emits biweekly deposits', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->biweekly()->create([
        'account_id' => $account->id,
        'next_expected_on' => '2026-07-03',
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-03'), CarbonImmutable::parse('2026-08-14'));

    expect(array_column($result, 'date'))->toBe(['2026-07-03', '2026-07-17', '2026-07-31', '2026-08-14']);
});

it('emits weekly deposits', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->weekly()->create([
        'account_id' => $account->id,
        'next_expected_on' => '2026-07-03',
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-03'), CarbonImmutable::parse('2026-07-24'));

    expect(array_column($result, 'date'))->toBe(['2026-07-03', '2026-07-10', '2026-07-17', '2026-07-24']);
});

it('emits semi-monthly deposits on primary and secondary days', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->semiMonthly()->create([
        'account_id' => $account->id,
        'next_expected_on' => '2026-07-01',
        'primary_day_of_month' => 1,
        'secondary_day_of_month' => 15,
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-08-15'));

    expect(array_column($result, 'date'))->toBe(['2026-07-01', '2026-07-15', '2026-08-01', '2026-08-15']);
});

it('clamps monthly day to last-of-month for short months', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'monthly',
        'next_expected_on' => '2026-01-31',
        'primary_day_of_month' => 31,
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-01-31'), CarbonImmutable::parse('2026-03-31'));

    expect(array_column($result, 'date'))->toBe(['2026-01-31', '2026-02-28', '2026-03-31']);
});

it('defaults secondary_day_of_month to 15 when null for semi_monthly source', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->create([
        'account_id' => $account->id,
        'cadence' => 'semi_monthly',
        'next_expected_on' => '2026-07-01',
        'primary_day_of_month' => 1,
        'secondary_day_of_month' => null, // should default to 15
        'expected_amount_cents' => 300000,
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-08-15'));

    // Expect: July 1, July 15, Aug 1, Aug 15
    expect(array_column($result, 'date'))->toBe(['2026-07-01', '2026-07-15', '2026-08-01', '2026-08-15']);
});

it('returns empty when anchor is past range end', function () {
    $account = Account::factory()->create();
    $source = IncomeSource::factory()->create([
        'account_id' => $account->id,
        'next_expected_on' => '2027-01-01',
    ]);

    $result = (new ProjectIncomeDeposits)([$source], CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-09-30'));

    expect($result)->toBe([]);
});
