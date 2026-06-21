<?php

use App\Models\Account;

it('casts starting_balance_cents to integer', function () {
    $a = Account::factory()->withStartingBalance(-150000)->create();
    expect($a->starting_balance_cents)->toBe(-150000);
});

it('casts counts_toward_goals to boolean', function () {
    $a = Account::factory()->countsTowardGoals()->create();
    expect($a->counts_toward_goals)->toBeTrue();
});

it('casts import_profile to array', function () {
    $a = Account::factory()->create([
        'import_profile' => ['date_column' => 'Date', 'has_header' => true],
    ]);

    expect($a->import_profile)->toBe(['date_column' => 'Date', 'has_header' => true]);
});

it('scopes active to non-archived accounts', function () {
    Account::factory()->count(2)->create();
    Account::factory()->archived()->create();

    expect(Account::active()->count())->toBe(2);
});

it('reports archived status', function () {
    expect(Account::factory()->archived()->create()->isArchived())->toBeTrue();
    expect(Account::factory()->create()->isArchived())->toBeFalse();
});
