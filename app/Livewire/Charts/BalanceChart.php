<?php

namespace App\Livewire\Charts;

use App\Actions\Finance\Balance\ComputeBalanceSeries;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Livewire\Component;

class BalanceChart extends Component
{
    public ?int $accountId = null;

    public string $range = '30d';

    public function setRange(string $range): void
    {
        $this->range = $range;
    }

    /**
     * @return array{start: string, end: string}
     */
    private function resolveRange(): array
    {
        $end = CarbonImmutable::today();

        $start = match ($this->range) {
            '90d' => $end->subDays(89),
            'ytd' => $end->startOfYear(),
            'all' => Transaction::query()
                ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
                ->min('occurred_on'),
            default => $end->subDays(29),
        };

        $start = $start ? CarbonImmutable::parse($start) : $end->subDays(29);

        return ['start' => $start->toDateString(), 'end' => $end->toDateString()];
    }

    public function render()
    {
        $accounts = $this->accountId
            ? Account::where('id', $this->accountId)->get()->all()
            : Account::active()->get()->all();

        $range = $this->resolveRange();
        $series = (new ComputeBalanceSeries)($accounts, $range['start'], $range['end']);

        $chart = [
            'chart' => ['type' => 'area', 'height' => 280, 'toolbar' => ['show' => false], 'animations' => ['enabled' => false]],
            'series' => [[
                'name' => 'Balance',
                'data' => array_map(fn ($p) => ['x' => $p['date'], 'y' => $p['balance_cents'] / 100], $series),
            ]],
            'xaxis' => ['type' => 'datetime'],
            'yaxis' => ['labels' => ['formatter' => 'function (v) { return "$" + v.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}); }']],
            'stroke' => ['curve' => 'stepline'],
            'dataLabels' => ['enabled' => false],
            'tooltip' => ['x' => ['format' => 'yyyy-MM-dd']],
        ];

        return view('livewire.charts.balance-chart', [
            'chart' => $chart,
            'range' => $this->range,
        ]);
    }
}
