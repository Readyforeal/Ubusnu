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

it('lists existing bills with cadence + due date label', function () {
    Bill::factory()->create(['name' => 'Mortgage', 'cadence' => 'monthly', 'due_day_of_month' => 1]);
    Bill::factory()->annual()->create(['name' => 'Property Tax', 'due_month_of_year' => 11, 'due_day_of_month' => 1]);

    Livewire::test('pages::bills.index')
        ->assertOk()
        ->assertSee('Mortgage')
        ->assertSee('Property Tax');
});

it('marks a bill paid this period via component method', function () {
    $bill = Bill::factory()->create(['cadence' => 'monthly']);

    Livewire::test('pages::bills.index')->call('markPaid', $bill->id);

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-06']);
});

it('unmarks a bill paid this period', function () {
    $bill = Bill::factory()->create(['cadence' => 'monthly', 'manually_marked_paid_periods' => '2026-06']);

    Livewire::test('pages::bills.index')->call('unmarkPaid', $bill->id);

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe([]);
});

it('opens the form via startEdit and closes on bill-saved event', function () {
    $bill = Bill::factory()->create();

    Livewire::test('pages::bills.index')
        ->call('startEdit', $bill->id)
        ->assertSet('editingId', $bill->id)
        ->call('closeForm')
        ->assertSet('editingId', null);
});

it('deletes a bill via component method', function () {
    $bill = Bill::factory()->create();

    Livewire::test('pages::bills.index')->call('deleteBill', $bill->id);

    expect(Bill::find($bill->id))->toBeNull();
});

it('runs RematchUnlinkedBills on rematch action', function () {
    $bill = Bill::factory()->create(['match_description' => 'COMCAST']);
    Transaction::factory()->create(['description' => 'COMCAST XFINITY', 'bill_id' => null]);

    Livewire::test('pages::bills.index')->call('rematch');

    expect(Transaction::where('description', 'COMCAST XFINITY')->first()->bill_id)->toBe($bill->id);
});

it('requires authentication', function () {
    auth()->logout();
    $this->get(route('bills.index'))->assertRedirect(route('login'));
});
