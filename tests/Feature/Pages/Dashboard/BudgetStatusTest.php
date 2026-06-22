<?php

use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    AppSetting::current()->update(['monthly_income_target_cents' => 500000]);
});

it('shows the income line', function () {
    Livewire::test('pages::dashboard.budget-status')
        ->assertSee('Income')
        ->assertSee('$5,000.00');
});

it('shows a row per bucket with name and target', function () {
    Bucket::factory()->create(['name' => 'Essentials', 'target_percentage' => 50]);
    Bucket::factory()->create(['name' => 'Lifestyle', 'target_percentage' => 30]);

    Livewire::test('pages::dashboard.budget-status')
        ->assertSee('Essentials')
        ->assertSee('$2,500.00')
        ->assertSee('Lifestyle')
        ->assertSee('$1,500.00');
});

it('hides the Unassigned row when there is no unassigned spending', function () {
    Livewire::test('pages::dashboard.budget-status')
        ->assertDontSee('Unassigned');
});

it('shows the Unassigned row when spending categories without a bucket have transactions', function () {
    $cat = Category::factory()->create();
    Transaction::factory()->create([
        'category_id' => $cat->id,
        'amount_cents' => -4500,
        'occurred_on' => now()->toDateString(),
    ]);

    Livewire::test('pages::dashboard.budget-status')
        ->assertSee('Unassigned')
        ->assertSee('$45.00');
});
