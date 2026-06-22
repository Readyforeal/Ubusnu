<?php

use App\Actions\Finance\Budgets\UpdateBucket;
use App\Models\Bucket;

it('updates allowed attributes', function () {
    $bucket = Bucket::factory()->create(['name' => 'Old', 'target_percentage' => 10]);

    (new UpdateBucket)($bucket, [
        'name' => 'New',
        'target_percentage' => 25,
        'color' => '#abcdef',
    ]);

    $bucket->refresh();
    expect($bucket->name)->toBe('New');
    expect($bucket->target_percentage)->toBe(25);
    expect($bucket->color)->toBe('#abcdef');
});

it('ignores attributes not in the allowed list', function () {
    $bucket = Bucket::factory()->create();

    (new UpdateBucket)($bucket, ['id' => 999, 'name' => 'Renamed']);

    $bucket->refresh();
    expect($bucket->name)->toBe('Renamed');
    expect($bucket->id)->not->toBe(999);
});
