<?php

use App\Models\AppSetting;
use App\Models\Bucket;
use App\Models\Category;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('lists existing buckets', function () {
    Bucket::factory()->create(['name' => 'Essentials']);
    Bucket::factory()->create(['name' => 'Lifestyle']);

    Livewire::test('pages::buckets.index')
        ->assertOk()
        ->assertSee('Essentials')
        ->assertSee('Lifestyle');
});

it('shows the monthly income target', function () {
    AppSetting::current()->update(['monthly_income_target_cents' => 500000]);

    Livewire::test('pages::buckets.index')
        ->assertSee('$5,000.00');
});

it('saves a new monthly income target', function () {
    Livewire::test('pages::buckets.index')
        ->set('incomeTargetDollars', '5000')
        ->call('applyIncomeTarget')
        ->assertHasNoErrors();

    expect(AppSetting::current()->monthly_income_target_cents)->toBe(500000);
});

it('opens the form via startEdit and closes on bucket-saved event', function () {
    $bucket = Bucket::factory()->create();

    Livewire::test('pages::buckets.index')
        ->call('startEdit', $bucket->id)
        ->assertSet('editingId', $bucket->id)
        ->call('closeForm')
        ->assertSet('editingId', null);
});

it('deletes a bucket and unassigns its categories', function () {
    $bucket = Bucket::factory()->create();
    $category = Category::factory()->inBucket($bucket)->create();

    Livewire::test('pages::buckets.index')
        ->call('deleteBucket', $bucket->id);

    expect(Bucket::find($bucket->id))->toBeNull();
    expect($category->fresh()->bucket_id)->toBeNull();
});

it('requires authentication', function () {
    auth()->logout();
    $this->get(route('buckets.index'))->assertRedirect(route('login'));
});
