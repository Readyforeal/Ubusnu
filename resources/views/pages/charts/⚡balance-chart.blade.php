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
            'series' => [[
                'name' => 'Balance',
                'data' => array_map(fn ($p) => ['x' => $p['date'], 'y' => $p['balance_cents'] / 100], $series),
            ]],
        ];
    }
}; ?>

<div>
    <div class="flex justify-end gap-2 mb-3 items-center flex-wrap">
        @if ($range === 'custom')
            <input type="date" wire:model.live.debounce.500ms="customStart" class="input input-xs input-bordered" />
            <span class="text-xs opacity-60">to</span>
            <input type="date" wire:model.live.debounce.500ms="customEnd" class="input input-xs input-bordered" />
        @endif
        <div class="join">
            @foreach (['30d' => '30D', '90d' => '90D', 'ytd' => 'YTD', 'all' => 'All', 'custom' => 'Custom'] as $key => $label)
                <button type="button" class="btn btn-xs join-item {{ $range === $key ? 'btn-primary' : 'btn-ghost' }}" wire:click="setRange('{{ $key }}')">{{ $label }}</button>
            @endforeach
        </div>
    </div>
    <div
        x-data="{
            chart: null,
            observer: null,
            renderChart() {
                if (this.chart) this.chart.destroy();
                this.chart = new ApexCharts($refs.chart, this.buildOptions(this.$wire.chart));
                this.chart.render();
            },
            init() {
                this.renderChart();
                this.$watch('$wire.chart', () => this.renderChart());
                this.observer = new MutationObserver(() => this.renderChart());
                this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
            },
            destroy() {
                if (this.observer) this.observer.disconnect();
                if (this.chart) { this.chart.destroy(); this.chart = null; }
            },
            readThemeColors() {
                // Read daisyUI CSS variables directly. Format varies across daisyUI 4/5
                // (raw oklch triples vs already-wrapped). Normalize through a hidden
                // probe to get rgb() strings regardless of source format.
                const style = getComputedStyle(document.documentElement);
                const getVar = (...names) => {
                    for (const n of names) {
                        const v = style.getPropertyValue(n).trim();
                        if (v) {
                            return v.startsWith('oklch(') || v.startsWith('#') || v.startsWith('rgb') ? v : `oklch(${v})`;
                        }
                    }
                    return null;
                };
                const probe = document.createElement('div');
                probe.style.position = 'absolute';
                probe.style.visibility = 'hidden';
                probe.style.pointerEvents = 'none';
                document.body.appendChild(probe);
                const toRgb = (color) => {
                    if (!color) return 'rgb(0, 0, 0)';
                    probe.style.color = '';
                    probe.style.color = color;
                    return getComputedStyle(probe).color;
                };
                const colors = {
                    primary: toRgb(getVar('--color-primary', '--p')),
                    baseContent: toRgb(getVar('--color-base-content', '--bc')),
                    base100: toRgb(getVar('--color-base-100', '--b1')),
                };
                probe.remove();
                return colors;
            },
            isDark(rgb) {
                const m = rgb.match(/\d+/g);
                if (!m || m.length < 3) return false;
                const lum = (0.299 * +m[0] + 0.587 * +m[1] + 0.114 * +m[2]) / 255;
                return lum < 0.5;
            },
            withAlpha(rgb, a) {
                const m = rgb.match(/\d+/g);
                if (!m || m.length < 3) return rgb;
                return `rgba(${m[0]}, ${m[1]}, ${m[2]}, ${a})`;
            },
            buildOptions(cfg) {
                const out = JSON.parse(JSON.stringify(cfg || {}));
                const c = this.readThemeColors();
                const dark = this.isDark(c.base100);

                out.chart = Object.assign({
                    type: 'area',
                    height: 280,
                    toolbar: { show: false },
                    animations: { enabled: false },
                    zoom: { enabled: false },
                    background: 'transparent',
                    fontFamily: 'Instrument Sans, ui-sans-serif, system-ui, sans-serif',
                    foreColor: c.baseContent,
                }, out.chart || {});

                out.colors = [c.primary];

                out.stroke = { curve: 'smooth', width: 2, lineCap: 'round' };

                out.fill = {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 0,
                        opacityFrom: 0.35,
                        opacityTo: 0.02,
                        stops: [0, 100],
                    },
                };

                out.dataLabels = { enabled: false };

                out.grid = {
                    borderColor: this.withAlpha(c.baseContent, 0.08),
                    strokeDashArray: 4,
                    xaxis: { lines: { show: false } },
                    yaxis: { lines: { show: true } },
                    padding: { top: 0, right: 8, bottom: 0, left: 8 },
                };

                out.xaxis = Object.assign({}, out.xaxis || {}, {
                    type: 'datetime',
                    axisBorder: { show: false },
                    axisTicks: { show: false },
                    labels: { style: { fontSize: '11px' } },
                });

                out.yaxis = {
                    labels: {
                        style: { fontSize: '11px' },
                        formatter: (v) => {
                            if (Math.abs(v) >= 1000) return '$' + (v / 1000).toFixed(1) + 'k';
                            return '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                        },
                    },
                };

                out.tooltip = {
                    theme: dark ? 'dark' : 'light',
                    x: { format: 'MMM dd, yyyy' },
                    y: {
                        formatter: (v) => '$' + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
                        title: { formatter: () => 'Balance' },
                    },
                    marker: { show: false },
                };

                out.markers = { size: 0, hover: { size: 4 } };

                out.theme = { mode: dark ? 'dark' : 'light' };

                return out;
            },
        }"
        wire:ignore
    >
        <div x-ref="chart"></div>
    </div>
</div>
