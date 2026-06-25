<?php

namespace App\Services\Coach;

interface CoachDriver
{
    public function name(): string;

    /**
     * Stream a single round of model completion as normalized chunks.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, CoachTool>  $tools
     * @return \Generator<StreamChunk>
     */
    public function stream(array $messages, array $tools): \Generator;
}
