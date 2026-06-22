<?php

use App\Actions\Finance\Imports\ImportTransactions;
use App\Models\Account;
use App\Models\Bill;
use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;

it('creates an import batch and persists new rows', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $rows = [
        [
            'occurred_on' => '2026-06-01',
            'description' => 'Coffee',
            'amount_cents' => -450,
            'dedup_hash' => 'h1',
            'category_id' => null,
            'status' => 'new',
        ],
        [
            'occurred_on' => '2026-06-02',
            'description' => 'Paycheck',
            'amount_cents' => 250000,
            'dedup_hash' => 'h2',
            'category_id' => null,
            'status' => 'new',
        ],
    ];

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch)->toBeInstanceOf(ImportBatch::class);
    expect($batch->imported_count)->toBe(2);
    expect($batch->skipped_duplicate_count)->toBe(0);
    expect($batch->error_count)->toBe(0);
    expect(Transaction::where('import_batch_id', $batch->id)->count())->toBe(2);
});

it('skips rows marked duplicate', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $rows = [
        ['occurred_on' => '2026-06-01', 'description' => 'A', 'amount_cents' => 100, 'dedup_hash' => 'h1', 'category_id' => null, 'status' => 'new'],
        ['occurred_on' => '2026-06-02', 'description' => 'B', 'amount_cents' => 200, 'dedup_hash' => 'h2', 'category_id' => null, 'status' => 'duplicate'],
    ];

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch->imported_count)->toBe(1);
    expect($batch->skipped_duplicate_count)->toBe(1);
});

it('counts error rows but does not persist them', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $rows = [
        ['occurred_on' => '2026-06-01', 'description' => 'A', 'amount_cents' => 100, 'dedup_hash' => 'h1', 'category_id' => null, 'status' => 'new'],
        ['occurred_on' => null, 'description' => 'B', 'amount_cents' => null, 'dedup_hash' => null, 'category_id' => null, 'status' => 'error'],
    ];

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch->imported_count)->toBe(1);
    expect($batch->error_count)->toBe(1);
});

it('catches DB unique constraint violations as duplicates', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();

    Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'description' => 'Existing',
        'amount_cents' => 100,
        'dedup_hash' => 'collision',
    ]);

    $rows = [
        ['occurred_on' => '2026-06-01', 'description' => 'Pretend new', 'amount_cents' => 100, 'dedup_hash' => 'collision', 'category_id' => null, 'status' => 'new'],
    ];

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch->imported_count)->toBe(0);
    expect($batch->error_count)->toBe(1);
});

it('records row_count from the input total regardless of status', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $rows = array_fill(0, 5, [
        'occurred_on' => '2026-06-01', 'description' => 'X', 'amount_cents' => 100, 'dedup_hash' => uniqid(), 'category_id' => null, 'status' => 'new',
    ]);

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch->row_count)->toBe(5);
});

it('persists bill_id from preview rows', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create();
    $bill = Bill::factory()->create();

    $rows = [[
        'occurred_on' => '2026-06-01',
        'description' => 'STARBUCKS',
        'amount_cents' => -450,
        'dedup_hash' => 'h1',
        'category_id' => null,
        'bill_id' => $bill->id,
        'status' => 'new',
    ]];

    $batch = (new ImportTransactions)($account, $rows, $user->id, 'sample.csv');

    expect($batch->imported_count)->toBe(1);
    expect(Transaction::where('description', 'STARBUCKS')->first()->bill_id)->toBe($bill->id);
});
