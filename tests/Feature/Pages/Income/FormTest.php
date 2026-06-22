<?php

use App\Models\Account;
use App\Models\IncomeSource;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates an income source', function () {
    $account = Account::factory()->create();

    Livewire::test('pages::income.form', ['sourceId' => 0])
        ->set('name', 'Paycheck')
        ->set('cadence', 'biweekly')
        ->set('nextExpectedOn', '2026-07-10')
        ->set('expectedDollars', '2500')
        ->set('accountId', $account->id)
        ->call('saveSource')
        ->assertHasNoErrors()
        ->assertDispatched('income-saved');

    expect(IncomeSource::where('name', 'Paycheck')->first())->not->toBeNull();
});

it('persists primary/secondary days for semi_monthly cadence', function () {
    $account = Account::factory()->create();

    Livewire::test('pages::income.form', ['sourceId' => 0])
        ->set('name', 'Salary')
        ->set('cadence', 'semi_monthly')
        ->set('nextExpectedOn', '2026-07-01')
        ->set('primaryDayOfMonth', 1)
        ->set('secondaryDayOfMonth', 15)
        ->set('expectedDollars', '2500')
        ->set('accountId', $account->id)
        ->call('saveSource')
        ->assertHasNoErrors();

    $source = IncomeSource::where('name', 'Salary')->first();
    expect($source->primary_day_of_month)->toBe(1);
    expect($source->secondary_day_of_month)->toBe(15);
});

it('clears semi_monthly fields when cadence is not semi_monthly', function () {
    $account = Account::factory()->create();

    Livewire::test('pages::income.form', ['sourceId' => 0])
        ->set('name', 'Weekly Pay')
        ->set('cadence', 'weekly')
        ->set('nextExpectedOn', '2026-07-10')
        ->set('primaryDayOfMonth', 1)
        ->set('secondaryDayOfMonth', 15)
        ->set('expectedDollars', '500')
        ->set('accountId', $account->id)
        ->call('saveSource');

    $source = IncomeSource::where('name', 'Weekly Pay')->first();
    // primary_day_of_month is auto-populated from next_expected_on by the model's booted hook
    expect($source->primary_day_of_month)->toBe(10);
    expect($source->secondary_day_of_month)->toBeNull();
});

it('requires a name', function () {
    Livewire::test('pages::income.form', ['sourceId' => 0])
        ->set('name', '')
        ->call('saveSource')
        ->assertHasErrors(['name']);
});
