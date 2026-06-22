<?php

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
