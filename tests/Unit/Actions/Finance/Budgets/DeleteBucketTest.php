<?php

use App\Actions\Finance\Budgets\DeleteBucket;
use App\Models\Bucket;
use App\Models\Category;

it('deletes the bucket', function () {
    $bucket = Bucket::factory()->create();

    (new DeleteBucket)($bucket);

    expect(Bucket::find($bucket->id))->toBeNull();
});

it('unassigns categories that referenced the bucket (nullOnDelete)', function () {
    $bucket = Bucket::factory()->create();
    $category = Category::factory()->inBucket($bucket)->create();

    (new DeleteBucket)($bucket);

    expect($category->fresh()->bucket_id)->toBeNull();
    expect($category->fresh()->kind)->toBe('spending');
});
