<?php

use App\Models\Bill;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    CarbonImmutable::setTestNow('2026-06-15');
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('hides the widget content when no bills exist', function () {
    Livewire::test('pages::dashboard.upcoming-bills')
        ->assertDontSee('Upcoming bills');
});

it('shows bills due within the next 14 days', function () {
    Bill::factory()->create(['name' => 'Mortgage', 'cadence' => 'monthly', 'due_day_of_month' => 20]);
    Bill::factory()->create(['name' => 'Annual Tax', 'cadence' => 'annual', 'due_month_of_year' => 11, 'due_day_of_month' => 1]);

    Livewire::test('pages::dashboard.upcoming-bills')
        ->assertSee('Mortgage')
        ->assertDontSee('Annual Tax');
});

it('shows paid badge for bills that are paid this period', function () {
    $bill = Bill::factory()->create(['name' => 'Rent', 'cadence' => 'monthly', 'due_day_of_month' => 20]);
    Transaction::factory()->create([
        'bill_id' => $bill->id,
        'occurred_on' => '2026-06-10',
    ]);

    Livewire::test('pages::dashboard.upcoming-bills')
        ->assertSee('Rent')
        ->assertSee('Paid');
});

it('hides bills due more than 14 days out', function () {
    Bill::factory()->create(['name' => 'Far-Future Bill', 'cadence' => 'monthly', 'due_day_of_month' => 10]);

    // today 6/15, due 10 → next due 7/10 (25 days out)
    Livewire::test('pages::dashboard.upcoming-bills')
        ->assertDontSee('Far-Future Bill');
});
