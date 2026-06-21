<?php

use App\Livewire\Imports\Wizard;
use App\Models\Account;
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
