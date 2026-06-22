<?php

use App\Models\Account;
use App\Models\Bill;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Database\UniqueConstraintViolationException;

it('belongs to an account', function () {
    $tx = Transaction::factory()->create();
    expect($tx->account)->toBeInstanceOf(Account::class);
});

it('can belong to a category', function () {
    $cat = Category::factory()->create();
    $tx = Transaction::factory()->create(['category_id' => $cat->id]);
    expect($tx->category->id)->toBe($cat->id);
});

it('auto-computes dedup_hash on create', function () {
    $tx = Transaction::factory()->create([
        'occurred_on' => '2026-06-01',
        'amount_cents' => 1234,
        'description' => 'Coffee',
    ]);

    expect($tx->dedup_hash)->toHaveLength(64);
});

it('produces same dedup_hash for identical rows on the same account', function () {
    $account = Account::factory()->create();
    $tx1 = Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'amount_cents' => 1234,
        'description' => 'Coffee',
    ]);

    expect(fn () => Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'amount_cents' => 1234,
        'description' => 'Coffee',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('soft-deletes and excludes from default queries', function () {
    $tx = Transaction::factory()->create();
    $tx->delete();

    expect(Transaction::find($tx->id))->toBeNull();
    expect(Transaction::withTrashed()->find($tx->id))->not->toBeNull();
});

it('casts occurred_on to a date', function () {
    $tx = Transaction::factory()->create(['occurred_on' => '2026-06-01']);
    expect($tx->occurred_on->format('Y-m-d'))->toBe('2026-06-01');
});

it('belongsTo a bill when bill_id is set', function () {
    $bill = Bill::factory()->create();
    $tx = Transaction::factory()->create(['bill_id' => $bill->id]);

    expect($tx->bill->id)->toBe($bill->id);
});

it('has null bill when bill_id is null', function () {
    $tx = Transaction::factory()->create();

    expect($tx->bill)->toBeNull();
});
