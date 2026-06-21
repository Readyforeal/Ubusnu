<?php

use App\Livewire\Imports\Wizard;
use App\Models\Account;
use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

it('starts on the upload step', function () {
    Livewire::test(Wizard::class)
        ->assertSet('step', 'upload');
});

it('moves to map step after upload when account has no profile', function () {
    $account = Account::factory()->create();
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->assertSet('step', 'map')
        ->assertSet('detectedHeaders', ['Date', 'Description', 'Amount']);
});

it('skips map step when account already has matching profile', function () {
    $account = Account::factory()->create([
        'import_profile' => [
            'delimiter' => ',',
            'has_header' => true,
            'date_column' => 'Date',
            'date_format' => 'm/d/Y',
            'description_column' => 'Description',
            'amount_column' => 'Amount',
        ],
    ]);
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->assertSet('step', 'preview');
});

it('saves the mapping to the account and proceeds to preview', function () {
    $account = Account::factory()->create();
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->set('mapDateColumn', 'Date')
        ->set('mapDateFormat', 'm/d/Y')
        ->set('mapDescriptionColumn', 'Description')
        ->set('mapAmountColumn', 'Amount')
        ->set('mapHasHeader', true)
        ->call('proceedFromMap')
        ->assertSet('step', 'preview');

    expect($account->fresh()->import_profile['date_column'])->toBe('Date');
});

it('shows preview rows after mapping', function () {
    $account = Account::factory()->create();
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    $component = Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->set('mapDateColumn', 'Date')
        ->set('mapDateFormat', 'm/d/Y')
        ->set('mapDescriptionColumn', 'Description')
        ->set('mapAmountColumn', 'Amount')
        ->set('mapHasHeader', true)
        ->call('proceedFromMap');

    expect($component->get('previewRows'))->toHaveCount(3);
    expect($component->get('previewRows')[0]['description'])->toBe('Coffee Shop');
});

it('commits the import and creates a batch', function () {
    $account = Account::factory()->create();
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->set('mapDateColumn', 'Date')
        ->set('mapDateFormat', 'm/d/Y')
        ->set('mapDescriptionColumn', 'Description')
        ->set('mapAmountColumn', 'Amount')
        ->set('mapHasHeader', true)
        ->call('proceedFromMap')
        ->call('commit')
        ->assertSet('step', 'done');

    expect(ImportBatch::count())->toBe(1);
    expect(Transaction::count())->toBe(3);
});

it('lets the user toggle a duplicate row to be force-included', function () {
    $account = Account::factory()->create();
    Transaction::factory()->forAccount($account)->create([
        'occurred_on' => '2026-06-01',
        'description' => 'Coffee Shop',
        'amount_cents' => -450,
    ]);
    $file = UploadedFile::fake()->createWithContent('sample.csv', file_get_contents(base_path('tests/Fixtures/csv/sample-standard.csv')));

    $component = Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->set('mapDateColumn', 'Date')
        ->set('mapDateFormat', 'm/d/Y')
        ->set('mapDescriptionColumn', 'Description')
        ->set('mapAmountColumn', 'Amount')
        ->set('mapHasHeader', true)
        ->call('proceedFromMap');

    expect($component->get('previewRows')[0]['status'])->toBe('duplicate');

    $component->call('toggleRow', 0);
    expect($component->get('previewRows')[0]['status'])->toBe('new');
});

it('imports a CSV with no header row using positional column indices', function () {
    $account = Account::factory()->create();
    $contents = "04/01/2026,-12.33,26091001,5589 R & K COUNTRY STORE MEAD NE\n".
                "04/02/2026,-50.00,26091002,Another Merchant\n";
    $file = UploadedFile::fake()->createWithContent('noheader.csv', $contents);

    Livewire::test(Wizard::class)
        ->set('accountId', $account->id)
        ->set('upload', $file)
        ->call('proceedFromUpload')
        ->set('mapHasHeader', false)
        ->set('mapDateColumn', '0')
        ->set('mapDateFormat', 'm/d/Y')
        ->set('mapDescriptionColumn', '3')
        ->set('mapAmountColumn', '1')
        ->call('proceedFromMap')
        ->assertSet('step', 'preview')
        ->call('commit')
        ->assertSet('step', 'done');

    expect($account->fresh()->import_profile['has_header'])->toBeFalse();
    expect(Transaction::count())->toBe(2);
    expect(Transaction::first()->occurred_on->format('Y-m-d'))->toBe('2026-04-01');
});
