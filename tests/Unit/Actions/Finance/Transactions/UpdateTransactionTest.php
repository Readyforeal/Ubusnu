<?php

use App\Actions\Finance\Transactions\UpdateTransaction;
use App\Models\Transaction;

it('updates allowed transaction fields', function () {
    $tx = Transaction::factory()->create([
        'description' => 'Old',
        'amount_cents' => 100,
        'notes' => null,
    ]);

    (new UpdateTransaction)($tx, [
        'description' => 'New',
        'amount_cents' => 200,
        'notes' => 'A note',
    ]);

    $tx->refresh();
    expect($tx->description)->toBe('New');
    expect($tx->amount_cents)->toBe(200);
    expect($tx->notes)->toBe('A note');
});

it('recomputes dedup_hash when description, date, or amount changes', function () {
    $tx = Transaction::factory()->create([
        'description' => 'Old',
        'amount_cents' => 100,
    ]);
    $original = $tx->dedup_hash;

    (new UpdateTransaction)($tx, ['description' => 'New']);

    expect($tx->fresh()->dedup_hash)->not->toBe($original);
});
