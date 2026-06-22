<?php

use App\Actions\Finance\Budgets\CreateBucket;
use App\Models\Bucket;

it('creates a bucket with the given fields', function () {
    $bucket = (new CreateBucket)('Essentials', 50, '#22c55e');

    expect($bucket)->toBeInstanceOf(Bucket::class);
    expect($bucket->name)->toBe('Essentials');
    expect($bucket->target_percentage)->toBe(50);
    expect($bucket->color)->toBe('#22c55e');
});

it('accepts null color', function () {
    $bucket = (new CreateBucket)('Lifestyle', 30, null);

    expect($bucket->color)->toBeNull();
});
