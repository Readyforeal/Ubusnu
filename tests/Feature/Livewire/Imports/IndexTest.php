<?php

use App\Livewire\Imports\Index;
use App\Models\ImportBatch;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists active import batches', function () {
    ImportBatch::factory()->count(2)->create();
    ImportBatch::factory()->undone()->create();

    Livewire::test(Index::class)
        ->assertOk()
        ->assertViewHas('batches', fn ($b) => $b->count() === 2);
});

it('shows undone batches when toggled', function () {
    ImportBatch::factory()->undone()->count(2)->create();

    Livewire::test(Index::class)
        ->set('showUndone', true)
        ->assertViewHas('batches', fn ($b) => $b->count() === 2);
});

it('undoes a batch via component', function () {
    $batch = ImportBatch::factory()->create();
    Transaction::factory()->count(3)->create(['import_batch_id' => $batch->id]);

    Livewire::test(Index::class)->call('undo', $batch->id);

    expect($batch->fresh()->isUndone())->toBeTrue();
    expect(Transaction::where('import_batch_id', $batch->id)->count())->toBe(0);
});
