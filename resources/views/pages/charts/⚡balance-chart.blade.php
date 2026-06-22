<?php

use App\Actions\Finance\Balance\ComputeBalanceSeries;
use App\Models\Account;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Livewire\Component;

new class extends Component {
    public ?int $accountId = null;

    public string $range = '30d';

    public string $customStart = '';

    public string $customEnd = '';

    /** @var array<string, mixed> */
    public array $chart = [];

    public function mount(): void
    {
        $this->customEnd = CarbonImmutable::today()->toDateString();
        $this->customStart = CarbonImmutable::today()->subDays(29)->toDateString();
        $this->rebuildChart();
    }

    public function setRange(string $range): void
    {
        $this->range = $range;
        $this->rebuildChart();
    }

    public function updatedCustomStart(): void
    {
        if ($this->range === 'custom') {
            $this->rebuildChart();
        }
    }

    public function updatedCustomEnd(): void
    {
        if ($this->range === 'custom') {
            $this->rebuildChart();
        }
    }

    /**
     * @return array{start: string, end: string}
     */
    private function resolveRange(): array
    {
        $end = CarbonImmutable::today();

        if ($this->range === 'custom') {
            $start = $this->customStart ? CarbonImmutable::parse($this->customStart) : $end->subDays(29);
            $customEnd = $this->customEnd ? CarbonImmutable::parse($this->customEnd) : $end;

            return ['start' => $start->toDateString(), 'end' => $customEnd->toDateString()];
        }

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

    private function rebuildChart(): void
    {
        $accounts = $this->accountId
            ? Account::where('id', $this->accountId)->get()->all()
            : Account::active()->get()->all();

        $range = $this->resolveRange();
        $series = (new ComputeBalanceSeries)($accounts, $range['start'], $range['end']);

        $this->chart = [
            'chart' => ['type' => 'area', 'height' => 280, 'toolbar' => ['show' => false], 'animations' => ['enabled' => false]],
            'series' => [[
                'name' => 'Balance',
                'data' => array_map(fn ($p) => ['x' => $p['date'], 'y' => $p['balance_cents'] / 100], $series),
            ]],
            'xaxis' => ['type' => 'datetime'],
            'stroke' => ['curve' => 'stepline', 'width' => 2],
            'dataLabels' => ['enabled' => false],
            'tooltip' => ['x' => ['format' => 'yyyy-MM-dd']],
        ];
    }
}; ?>

<div>
    <div class="flex justify-end gap-1 mb-2 items-center flex-wrap">
        @if ($range === 'custom')
            <input type="date" wire:model.live.debounce.500ms="customStart" class="input input-xs input-bordered" />
            <span class="text-xs opacity-60">to</span>
            <input type="date" wire:model.live.debounce.500ms="customEnd" class="input input-xs input-bordered" />
        @endif
        @foreach (['30d' => '30D', '90d' => '90D', 'ytd' => 'YTD', 'all' => 'All', 'custom' => 'Custom'] as $key => $label)
            <x-button :label="$label" class="btn-xs {{ $range === $key ? 'btn-primary' : 'btn-ghost' }}" wire:click="setRange('{{ $key }}')" />
        @endforeach
    </div>
    <div
        x-data="{
            chart: null,
            init() {
                const cfg = this.withFormatters(this.$wire.chart);
                this.chart = new ApexCharts($refs.chart, cfg);
                this.chart.render();
                this.$watch('$wire.chart', (newCfg) => {
                    if (this.chart) this.chart.updateOptions(this.withFormatters(newCfg));
                });
            },
            withFormatters(cfg) {
                const out = JSON.parse(JSON.stringify(cfg));
                out.yaxis = out.yaxis || {};
                out.yaxis.labels = out.yaxis.labels || {};
                out.yaxis.labels.formatter = (v) => '$' + Number(v).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                return out;
            },
            destroy() {
                if (this.chart) { this.chart.destroy(); this.chart = null; }
            },
        }"
        wire:ignore
    >
        <div x-ref="chart"></div>
    </div>
</div>
