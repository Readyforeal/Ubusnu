<?php

use App\Models\Category;
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
