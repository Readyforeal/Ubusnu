<?php

use App\Livewire\Categories\Index;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

it('lists categories', function () {
    $this->actingAs(User::factory()->create());
    Category::factory()->count(3)->create();

    Livewire::test(Index::class)
        ->assertOk()
        ->assertViewHas('categories', fn ($cs) => $cs->count() >= 3);
});

it('requires authentication', function () {
    $this->get(route('categories.index'))->assertRedirect(route('login'));
});
