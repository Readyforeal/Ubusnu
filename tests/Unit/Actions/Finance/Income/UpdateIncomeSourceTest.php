<?php

use App\Actions\Finance\Income\UpdateIncomeSource;
use App\Models\IncomeSource;

it('updates the given income source', function () {
    $source = IncomeSource::factory()->create(['name' => 'Old']);

    $updated = (new UpdateIncomeSource)($source, ['name' => 'New']);

    expect($updated->name)->toBe('New');
    expect($source->fresh()->name)->toBe('New');
});
