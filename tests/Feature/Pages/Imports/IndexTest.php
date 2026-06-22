<?php

use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists active import batches', function () {
    ImportBatch::factory()->count(2)->create(['filename' => 'active.csv']);
    ImportBatch::factory()->undone()->create(['filename' => 'undone.csv']);

    Livewire::test('pages::imports.index')
        ->assertOk()
        ->assertSee('active.csv')
        ->assertDontSee('undone.csv');
});

it('shows undone batches when toggled', function () {
    ImportBatch::factory()->create(['filename' => 'active.csv']);
    ImportBatch::factory()->undone()->create(['filename' => 'undone.csv']);

    Livewire::test('pages::imports.index')
        ->set('showUndone', true)
        ->assertSee('undone.csv')
        ->assertDontSee('active.csv');
});

it('undoes a batch via component', function () {
    $batch = ImportBatch::factory()->create();
    Transaction::factory()->count(3)->create(['import_batch_id' => $batch->id]);

    Livewire::test('pages::imports.index')->call('undo', $batch->id);

    expect($batch->fresh()->isUndone())->toBeTrue();
    expect(Transaction::where('import_batch_id', $batch->id)->count())->toBe(0);
});
