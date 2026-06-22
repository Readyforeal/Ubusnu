<?php

use App\Models\Bill;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a new bill', function () {
    Livewire::test('pages::bills.form', ['billId' => 0])
        ->set('name', 'Mortgage')
        ->set('cadence', 'monthly')
        ->set('dueDayOfMonth', 1)
        ->set('expectedDollars', '2300')
        ->set('matchDescription', 'US BANK HOME MTG')
        ->call('saveBill')
        ->assertHasNoErrors();

    $bill = Bill::where('name', 'Mortgage')->first();
    expect($bill)->not->toBeNull();
    expect($bill->cadence)->toBe('monthly');
    expect($bill->due_day_of_month)->toBe(1);
    expect($bill->expected_amount_cents)->toBe(230000);
    expect($bill->match_description)->toBe('US BANK HOME MTG');
});

it('updates an existing bill', function () {
    $bill = Bill::factory()->create(['name' => 'Old']);

    Livewire::test('pages::bills.form', ['billId' => $bill->id])
        ->set('name', 'New')
        ->call('saveBill')
        ->assertHasNoErrors();

    expect($bill->fresh()->name)->toBe('New');
});

it('requires due_month_of_year when cadence is annual', function () {
    Livewire::test('pages::bills.form', ['billId' => 0])
        ->set('name', 'Annual Thing')
        ->set('cadence', 'annual')
        ->set('dueDayOfMonth', 15)
        ->set('dueMonthOfYear', null)
        ->set('expectedDollars', '100')
        ->call('saveBill')
        ->assertHasErrors(['dueMonthOfYear']);
});

it('requires name, cadence, due day, expected amount', function () {
    Livewire::test('pages::bills.form', ['billId' => 0])
        ->set('name', '')
        ->set('dueDayOfMonth', 0)
        ->set('expectedDollars', '0')
        ->call('saveBill')
        ->assertHasErrors(['name', 'dueDayOfMonth', 'expectedDollars']);
});

it('dispatches bill-saved on success', function () {
    Livewire::test('pages::bills.form', ['billId' => 0])
        ->set('name', 'Electric')
        ->set('cadence', 'monthly')
        ->set('dueDayOfMonth', 5)
        ->set('expectedDollars', '120')
        ->call('saveBill')
        ->assertDispatched('bill-saved');
});
