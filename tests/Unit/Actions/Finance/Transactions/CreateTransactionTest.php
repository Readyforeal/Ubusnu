<?php

use App\Actions\Finance\Transactions\CreateTransaction;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;

it('creates a manual transaction', function () {
    $account = Account::factory()->create();

    $tx = (new CreateTransaction)(
        account: $account,
        occurredOn: '2026-06-15',
        description: 'Lunch',
        amountCents: -1200,
        categoryId: null,
    );

    expect($tx)->toBeInstanceOf(Transaction::class);
    expect($tx->account_id)->toBe($account->id);
    expect($tx->amount_cents)->toBe(-1200);
    expect($tx->source)->toBe('manual');
    expect($tx->dedup_hash)->not->toBeEmpty();
});

it('attaches a category when provided', function () {
    $account = Account::factory()->create();
    $category = Category::factory()->create();

    $tx = (new CreateTransaction)(
        account: $account,
        occurredOn: '2026-06-15',
        description: 'Groceries',
        amountCents: -5000,
        categoryId: $category->id,
    );

    expect($tx->category_id)->toBe($category->id);
});
