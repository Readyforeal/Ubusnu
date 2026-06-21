<?php

use App\Actions\Finance\Transactions\CategorizeTransaction;
use App\Models\Category;
use App\Models\Transaction;

it('assigns a category to a transaction', function () {
    $tx = Transaction::factory()->create(['category_id' => null]);
    $cat = Category::factory()->create();

    (new CategorizeTransaction)($tx, $cat);

    expect($tx->fresh()->category_id)->toBe($cat->id);
});

it('clears the category when null is passed', function () {
    $cat = Category::factory()->create();
    $tx = Transaction::factory()->create(['category_id' => $cat->id]);

    (new CategorizeTransaction)($tx, null);

    expect($tx->fresh()->category_id)->toBeNull();
});
