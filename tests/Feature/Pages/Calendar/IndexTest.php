<?php

use App\Models\Account;
use App\Models\Bill;
use App\Models\IncomeSource;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders bills on the calendar with paid and unpaid pills', function () {
    $today = CarbonImmutable::today();

    $acct = Account::factory()->withStartingBalance(500000)->create(['minimum_balance_cents' => 0]);

    IncomeSource::factory()->create([
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'next_expected_on' => $today->addDays(2)->toDateString(),
        'expected_amount_cents' => 300000,
    ]);

    $unpaidBill = Bill::factory()->create([
        'name' => 'Spectrum',
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->addDays(10)->day,
        'expected_amount_cents' => 12000,
    ]);

    $paidBill = Bill::factory()->create([
        'name' => 'Mortgage',
        'account_id' => $acct->id,
        'cadence' => 'monthly',
        'due_day_of_month' => $today->day,
        'expected_amount_cents' => 150000,
        'manually_marked_paid_periods' => $today->format('Y-m'),
    ]);

    Transaction::factory()->create([
        'account_id' => $acct->id,
        'bill_id' => $paidBill->id,
        'occurred_on' => $today->toDateString(),
        'amount_cents' => -150000,
        'description' => 'US BANK HOME MTG',
    ]);

    $response = $this->get('/calendar?month='.$today->format('Y-m'))
        ->assertOk()
        ->assertSee('Spectrum')
        ->assertSee('Mortgage');

    // Paid pill includes the ✓ + amount class hooks
    expect($response->getContent())->toContain('opacity-50');
    expect($response->getContent())->toContain('✓');
});

it('shows the empty-state message when no bills are due', function () {
    $this->get('/calendar')
        ->assertOk()
        ->assertSee('All bills on track');
});
