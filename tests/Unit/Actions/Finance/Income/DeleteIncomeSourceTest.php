<?php

use App\Actions\Finance\Income\DeleteIncomeSource;
use App\Models\IncomeSource;

it('deletes the income source', function () {
    $source = IncomeSource::factory()->create();
    $id = $source->id;

    (new DeleteIncomeSource)($source);

    expect(IncomeSource::find($id))->toBeNull();
});
