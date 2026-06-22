<?php

use App\Actions\Finance\Goals\DeleteGoal;
use App\Models\Goal;

it('deletes the goal', function () {
    $goal = Goal::factory()->create();

    (new DeleteGoal)($goal);

    expect(Goal::find($goal->id))->toBeNull();
});
