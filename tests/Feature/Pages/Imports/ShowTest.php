<?php

use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('shows batch metadata', function () {
    $batch = ImportBatch::factory()->create([
        'filename' => 'march-statement.csv',
        'row_count' => 50,
        'imported_count' => 47,
        'skipped_duplicate_count' => 2,
        'error_count' => 1,
    ]);

    Livewire::test('pages::imports.show', ['batch' => $batch])
        ->assertOk()
        ->assertSee('march-statement.csv')
        ->assertSee('47')
        ->assertSee('2')
        ->assertSee('1');
});

it('lists transactions in this batch including soft-deleted ones', function () {
    $batch = ImportBatch::factory()->create();
    Transaction::factory()->count(2)->create(['import_batch_id' => $batch->id, 'description' => 'Active']);
    $tx = Transaction::factory()->create(['import_batch_id' => $batch->id, 'description' => 'WillBeDeleted']);
    $tx->delete();

    Livewire::test('pages::imports.show', ['batch' => $batch])
        ->assertSee('Active')
        ->assertSee('WillBeDeleted');
});

it('undoes the batch via component method', function () {
    $batch = ImportBatch::factory()->create();
    Transaction::factory()->count(3)->create(['import_batch_id' => $batch->id]);

    Livewire::test('pages::imports.show', ['batch' => $batch])
        ->call('undo');

    expect($batch->fresh()->isUndone())->toBeTrue();
    expect(Transaction::where('import_batch_id', $batch->id)->count())->toBe(0);
});
