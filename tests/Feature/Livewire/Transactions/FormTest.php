<?php

use App\Livewire\Transactions\Form;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a manual transaction', function () {
    $account = Account::factory()->create();

    Livewire::test(Form::class, ['transactionId' => 0])
        ->set('accountId', $account->id)
        ->set('occurredOn', '2026-06-15')
        ->set('description', 'Lunch')
        ->set('amountDollars', '-12.50')
        ->call('save')
        ->assertHasNoErrors();

    $tx = Transaction::where('description', 'Lunch')->first();
    expect($tx)->not->toBeNull();
    expect($tx->amount_cents)->toBe(-1250);
    expect($tx->source)->toBe('manual');
});

it('updates an existing transaction', function () {
    $tx = Transaction::factory()->create([
        'description' => 'Old',
        'amount_cents' => -1000,
    ]);

    Livewire::test(Form::class, ['transactionId' => $tx->id])
        ->set('description', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($tx->fresh()->description)->toBe('New');
});

it('requires account, date, description, amount', function () {
    Livewire::test(Form::class, ['transactionId' => 0])
        ->set('accountId', null)
        ->set('occurredOn', '')
        ->set('description', '')
        ->set('amountDollars', '')
        ->call('save')
        ->assertHasErrors(['accountId', 'occurredOn', 'description', 'amountDollars']);
});
