<?php

use App\Models\Bucket;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a new category', function () {
    Livewire::test('pages::categories.form', ['categoryId' => 0])
        ->set('name', 'Groceries')
        ->set('keywords', 'safeway, save-on, walmart')
        ->call('save')
        ->assertHasNoErrors();

    expect(Category::where('name', 'Groceries')->exists())->toBeTrue();
});

it('updates an existing category', function () {
    $cat = Category::factory()->create(['name' => 'Old']);

    Livewire::test('pages::categories.form', ['categoryId' => $cat->id])
        ->set('name', 'New')
        ->call('save')
        ->assertHasNoErrors();

    expect($cat->fresh()->name)->toBe('New');
});

it('requires a name', function () {
    Livewire::test('pages::categories.form', ['categoryId' => 0])
        ->set('name', '')
        ->call('save')
        ->assertHasErrors(['name']);
});

it('dispatches category-saved on successful save', function () {
    Livewire::test('pages::categories.form', ['categoryId' => 0])
        ->set('name', 'NewCat')
        ->call('save')
        ->assertDispatched('category-saved');
});

it('creates a category with kind=spending and a bucket', function () {
    $bucket = Bucket::factory()->create();

    Livewire::test('pages::categories.form', ['categoryId' => 0])
        ->set('name', 'Groceries')
        ->set('kind', 'spending')
        ->set('bucketId', $bucket->id)
        ->call('save')
        ->assertHasNoErrors();

    $cat = Category::where('name', 'Groceries')->first();
    expect($cat->kind)->toBe('spending');
    expect($cat->bucket_id)->toBe($bucket->id);
});

it('clears bucket_id when kind switches to income or transfer', function () {
    $bucket = Bucket::factory()->create();
    $cat = Category::factory()->inBucket($bucket)->create();

    Livewire::test('pages::categories.form', ['categoryId' => $cat->id])
        ->set('kind', 'income')
        ->call('save')
        ->assertHasNoErrors();

    expect($cat->fresh()->kind)->toBe('income');
    expect($cat->fresh()->bucket_id)->toBeNull();
});

it('persists kind=transfer with no bucket', function () {
    Livewire::test('pages::categories.form', ['categoryId' => 0])
        ->set('name', 'Internal Transfer')
        ->set('kind', 'transfer')
        ->call('save')
        ->assertHasNoErrors();

    $cat = Category::where('name', 'Internal Transfer')->first();
    expect($cat->kind)->toBe('transfer');
    expect($cat->bucket_id)->toBeNull();
});
