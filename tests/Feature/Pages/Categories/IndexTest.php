<?php

use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

it('lists categories', function () {
    $this->actingAs(User::factory()->create());
    Category::factory()->count(3)->create();

    Livewire::test('pages::categories.index')
        ->assertOk()
        ->assertSee(Category::orderBy('name')->first()->name);
});

it('requires authentication', function () {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
});

it('opens the form via startEdit and closes on saved event', function () {
    $this->actingAs(User::factory()->create());
    $cat = Category::factory()->create();

    Livewire::test('pages::categories.index')
        ->call('startEdit', $cat->id)
        ->assertSet('editingId', $cat->id)
        ->call('closeForm')
        ->assertSet('editingId', null);
});

it('shows a Recategorize uncategorized button', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::categories.index')
        ->assertSee('Recategorize uncategorized');
});

it('runs the matcher when the button is clicked and updates counts', function () {
    $this->actingAs(User::factory()->create());
    $coffee = Category::factory()->create(['keywords' => 'starbucks']);
    Transaction::factory()->create(['category_id' => null, 'description' => 'STARBUCKS #1']);
    Transaction::factory()->create(['category_id' => null, 'description' => 'MYSTERY VENDOR']);

    Livewire::test('pages::categories.index')
        ->call('recategorize')
        ->assertHasNoErrors();

    expect(Transaction::where('description', 'STARBUCKS #1')->first()->category_id)->toBe($coffee->id);
    expect(Transaction::where('description', 'MYSTERY VENDOR')->first()->category_id)->toBeNull();
});
