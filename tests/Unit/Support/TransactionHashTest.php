<?php

use App\Support\TransactionHash;

it('produces deterministic hash for identical inputs', function () {
    $a = TransactionHash::for(1, '2026-06-01', 1234, 'Coffee Shop');
    $b = TransactionHash::for(1, '2026-06-01', 1234, 'Coffee Shop');

    expect($a)->toBe($b);
});

it('normalizes whitespace in description', function () {
    $a = TransactionHash::for(1, '2026-06-01', 1234, '  Coffee   Shop  ');
    $b = TransactionHash::for(1, '2026-06-01', 1234, 'Coffee Shop');

    expect($a)->toBe($b);
});

it('normalizes case in description', function () {
    $a = TransactionHash::for(1, '2026-06-01', 1234, 'COFFEE SHOP');
    $b = TransactionHash::for(1, '2026-06-01', 1234, 'coffee shop');

    expect($a)->toBe($b);
});

it('produces different hash when any field differs', function () {
    $base = TransactionHash::for(1, '2026-06-01', 1234, 'Coffee');

    expect(TransactionHash::for(2, '2026-06-01', 1234, 'Coffee'))->not->toBe($base);
    expect(TransactionHash::for(1, '2026-06-02', 1234, 'Coffee'))->not->toBe($base);
    expect(TransactionHash::for(1, '2026-06-01', 1235, 'Coffee'))->not->toBe($base);
    expect(TransactionHash::for(1, '2026-06-01', 1234, 'Tea'))->not->toBe($base);
});

it('returns a 64-character sha256 hex string', function () {
    $hash = TransactionHash::for(1, '2026-06-01', 1234, 'X');

    expect($hash)->toHaveLength(64);
});
