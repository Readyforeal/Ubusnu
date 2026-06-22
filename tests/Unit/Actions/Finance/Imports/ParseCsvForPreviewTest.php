<?php

use App\Actions\Finance\Imports\ParseCsvForPreview;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;

beforeEach(function () {
    $this->profile = [
        'delimiter' => ',',
        'has_header' => true,
        'date_column' => 'Date',
        'date_format' => 'm/d/Y',
        'description_column' => 'Description',
        'amount_column' => 'Amount',
    ];
});

it('parses each non-header row into a structured preview row', function () {
    $account = Account::factory()->create();
    $path = base_path('tests/Fixtures/csv/sample-standard.csv');

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows)->toHaveCount(3);
    expect($rows[0]['occurred_on'])->toBe('2026-06-01');
    expect($rows[0]['description'])->toBe('Coffee Shop');
    expect($rows[0]['amount_cents'])->toBe(-450);
    expect($rows[0]['status'])->toBe('new');
});

it('marks duplicate rows already in DB', function () {
    $account = Account::factory()->create();
    Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'description' => 'Coffee Shop',
        'amount_cents' => -450,
    ]);

    $rows = (new ParseCsvForPreview)($account, base_path('tests/Fixtures/csv/sample-standard.csv'), $this->profile);

    expect($rows[0]['status'])->toBe('duplicate');
    expect($rows[0]['duplicate_of'])->not->toBeNull();
});

it('flags unparseable rows as errors', function () {
    $account = Account::factory()->create();

    $rows = (new ParseCsvForPreview)($account, base_path('tests/Fixtures/csv/sample-bad-rows.csv'), $this->profile);

    expect($rows[0]['status'])->toBe('new');
    expect($rows[1]['status'])->toBe('error');
    expect($rows[1]['error'])->toContain('date');
    expect($rows[2]['status'])->toBe('error');
    expect($rows[2]['error'])->toContain('amount');
    expect($rows[3]['status'])->toBe('new');
});

it('honours alternative column names from the profile', function () {
    $account = Account::factory()->create();
    $profile = [
        'delimiter' => ',',
        'has_header' => true,
        'date_column' => 'Posting Date',
        'date_format' => 'Y-m-d',
        'description_column' => 'Memo',
        'amount_column' => 'Trans Amount',
    ];

    $rows = (new ParseCsvForPreview)($account, base_path('tests/Fixtures/csv/sample-alt-headers.csv'), $profile);

    expect($rows)->toHaveCount(2);
    expect($rows[0]['description'])->toBe('Coffee Shop');
    expect($rows[0]['amount_cents'])->toBe(-450);
});

it('auto-categorizes import rows against any category keywords', function () {
    $account = Account::factory()->create();
    $coffee = Category::factory()->create(['name' => 'Coffee', 'keywords' => 'starbucks']);
    $gas = Category::factory()->create(['name' => 'Gas', 'keywords' => 'shell']);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents(
        $path,
        "Date,Description,Amount\n".
        "06/01/2026,STARBUCKS #1234,-4.50\n".
        "06/02/2026,SHELL OIL,-50.00\n".
        "06/03/2026,MYSTERY VENDOR,-10.00\n"
    );

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows[0]['category_id'])->toBe($coffee->id);
    expect($rows[1]['category_id'])->toBe($gas->id);
    expect($rows[2]['category_id'])->toBeNull();

    unlink($path);
});

it('leaves a row uncategorized when two categories ambiguously match', function () {
    $account = Account::factory()->create();
    Category::factory()->create(['name' => 'Shopping', 'keywords' => 'target']);
    Category::factory()->create(['name' => 'Groceries', 'keywords' => 'groceries']);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "Date,Description,Amount\n06/01/2026,TARGET STORE - GROCERIES,-30.00\n");

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows[0]['category_id'])->toBeNull();

    unlink($path);
});

it('handles rows with mismatched field counts as errors instead of crashing', function () {
    $account = Account::factory()->create();

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "Date,Description,Amount\n06/01/2026,Good Row,-10.00\n06/02/2026,Has,unquoted,comma,-5.00\n06/03/2026,Another Good,15.00\n");

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows)->toHaveCount(3);
    expect($rows[0]['status'])->toBe('new');
    expect($rows[1]['status'])->toBe('error');
    expect($rows[1]['error'])->toContain('Malformed');
    expect($rows[2]['status'])->toBe('new');

    unlink($path);
});

it('parses CSVs with no header row using positional column indices', function () {
    $account = Account::factory()->create();
    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path,
        "04/01/2026,-12.33,26091001,5589 R & K COUNTRY STORE MEAD NE\n".
        "04/02/2026,-50.00,26091002,Another Merchant\n"
    );

    $rows = (new ParseCsvForPreview)($account, $path, [
        'delimiter' => ',',
        'has_header' => false,
        'date_column' => '0',
        'date_format' => 'm/d/Y',
        'description_column' => '3',
        'amount_column' => '1',
    ]);

    expect($rows)->toHaveCount(2);
    expect($rows[0]['status'])->toBe('new');
    expect($rows[0]['occurred_on'])->toBe('2026-04-01');
    expect($rows[0]['description'])->toBe('5589 R & K COUNTRY STORE MEAD NE');
    expect($rows[0]['amount_cents'])->toBe(-1233);
    expect($rows[1]['occurred_on'])->toBe('2026-04-02');
    expect($rows[1]['amount_cents'])->toBe(-5000);

    unlink($path);
});
