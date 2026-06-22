<?php

use App\Models\Bucket;
use Illuminate\Database\Eloquent\Relations\HasMany;

it('persists bucket attributes', function () {
    $bucket = Bucket::factory()->create([
        'name' => 'Essentials',
        'target_percentage' => 50,
        'color' => '#22c55e',
        'sort_order' => 1,
    ]);

    expect($bucket->name)->toBe('Essentials');
    expect($bucket->target_percentage)->toBe(50);
    expect($bucket->color)->toBe('#22c55e');
    expect($bucket->sort_order)->toBe(1);
});

it('targetCents computes percentage of an income basis', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);

    expect($bucket->targetCents(500000))->toBe(250000);
    expect($bucket->targetCents(123456))->toBe(61728);
});

it('targetCents returns 0 when income is 0', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 50]);

    expect($bucket->targetCents(0))->toBe(0);
});

it('targetCents returns 0 when percentage is 0', function () {
    $bucket = Bucket::factory()->create(['target_percentage' => 0]);

    expect($bucket->targetCents(500000))->toBe(0);
});

it('has many categories', function () {
    $bucket = Bucket::factory()->create();

    expect($bucket->categories())->toBeInstanceOf(HasMany::class);
});
