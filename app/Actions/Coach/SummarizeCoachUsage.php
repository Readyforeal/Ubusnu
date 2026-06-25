<?php

namespace App\Actions\Coach;

use App\Models\ChatMessage;
use Carbon\CarbonImmutable;

class SummarizeCoachUsage
{
    public function __construct(private readonly EstimateCost $estimateCost) {}

    /**
     * @return array{
     *     today: array{input: int, output: int, cents: int},
     *     month: array{input: int, output: int, cents: int}
     * }
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();

        return [
            'today' => $this->summarize($today->startOfDay(), $today->endOfDay()),
            'month' => $this->summarize($today->startOfMonth(), $today->endOfMonth()),
        ];
    }

    /**
     * @return array{input: int, output: int, cents: int}
     */
    private function summarize(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $rows = ChatMessage::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('provider')
            ->whereNotNull('model')
            ->selectRaw('provider, model, COALESCE(SUM(input_tokens), 0) as input_total, COALESCE(SUM(output_tokens), 0) as output_total')
            ->groupBy('provider', 'model')
            ->get();

        $totalInput = 0;
        $totalOutput = 0;
        $totalCents = 0;

        foreach ($rows as $row) {
            $input = (int) $row->input_total;
            $output = (int) $row->output_total;
            $totalInput += $input;
            $totalOutput += $output;
            $totalCents += ($this->estimateCost)($row->provider, $row->model, $input, $output);
        }

        return ['input' => $totalInput, 'output' => $totalOutput, 'cents' => $totalCents];
    }
}
