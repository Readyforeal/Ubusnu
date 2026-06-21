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

it('auto-categorizes rows matching Transfer keywords', function () {
    $account = Account::factory()->create();
    $transfer = Category::factory()->create([
        'name' => 'Transfer',
        'keywords' => 'transfer, tfr',
        'excluded_from_totals' => true,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'csv');
    file_put_contents($path, "Date,Description,Amount\n06/01/2026,E-Transfer to John,-100.00\n");

    $rows = (new ParseCsvForPreview)($account, $path, $this->profile);

    expect($rows[0]['category_id'])->toBe($transfer->id);

    unlink($path);
});
