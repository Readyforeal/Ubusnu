<?php

use App\Models\Bill;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows bill metadata', function () {
    $bill = Bill::factory()->create([
        'name' => 'US Bank Mortgage',
        'cadence' => 'monthly',
        'expected_amount_cents' => 230000,
    ]);

    Livewire::test('pages::bills.show', ['bill' => $bill])
        ->assertOk()
        ->assertSee('US Bank Mortgage')
        ->assertSee('$2,300.00');
});

it('lists transactions linked to this bill', function () {
    $bill = Bill::factory()->create();
    Transaction::factory()->create(['bill_id' => $bill->id, 'description' => 'Linked One']);
    Transaction::factory()->create(['bill_id' => $bill->id, 'description' => 'Linked Two']);
    Transaction::factory()->create(['description' => 'Unrelated']);

    Livewire::test('pages::bills.show', ['bill' => $bill])
        ->assertSee('Linked One')
        ->assertSee('Linked Two')
        ->assertDontSee('Unrelated');
});

it('lists manually-marked periods', function () {
    $bill = Bill::factory()->create(['manually_marked_paid_periods' => '2026-04,2026-05']);

    Livewire::test('pages::bills.show', ['bill' => $bill])
        ->assertSee('2026-04')
        ->assertSee('2026-05');
});

it('removes a manually-marked period via component method', function () {
    $bill = Bill::factory()->create(['manually_marked_paid_periods' => '2026-04,2026-05']);

    Livewire::test('pages::bills.show', ['bill' => $bill])
        ->call('removePeriod', '2026-04');

    expect($bill->fresh()->manuallyMarkedPeriods())->toBe(['2026-05']);
});
