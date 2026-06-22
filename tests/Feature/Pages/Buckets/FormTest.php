<?php

use App\Models\Bucket;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a new bucket', function () {
    Livewire::test('pages::buckets.form', ['bucketId' => 0])
        ->set('name', 'Essentials')
        ->set('targetPercentage', 50)
        ->set('color', '#22c55e')
        ->call('saveBucket')
        ->assertHasNoErrors();

    expect(Bucket::where('name', 'Essentials')->exists())->toBeTrue();
});

it('updates an existing bucket', function () {
    $bucket = Bucket::factory()->create(['name' => 'Old']);

    Livewire::test('pages::buckets.form', ['bucketId' => $bucket->id])
        ->set('name', 'New')
        ->call('saveBucket')
        ->assertHasNoErrors();

    expect($bucket->fresh()->name)->toBe('New');
});

it('requires name and target_percentage within range', function () {
    Livewire::test('pages::buckets.form', ['bucketId' => 0])
        ->set('name', '')
        ->set('targetPercentage', 150)
        ->call('saveBucket')
        ->assertHasErrors(['name', 'targetPercentage']);
});

it('dispatches bucket-saved on success', function () {
    Livewire::test('pages::buckets.form', ['bucketId' => 0])
        ->set('name', 'Lifestyle')
        ->set('targetPercentage', 30)
        ->call('saveBucket')
        ->assertDispatched('bucket-saved');
});
