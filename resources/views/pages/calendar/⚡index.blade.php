<?php

use App\Actions\Finance\Forecast\ComputeProjectedBalance;
use App\Actions\Finance\Forecast\RecommendPayDates;
use App\Models\Account;
use App\Models\Bill;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Calendar')] class extends Component {
    #[Url(as: 'month')]
    public string $monthKey = '';

    /** @var array<int, array{day: int, in_month: bool, date: string, is_today: bool, items: array<int, array{type: string, bill_id: int, label: string}>}> */
    public array $cells = [];

    /** @var array<int, array{bill: string, due: string, warning: bool}> */
    public array $thisWeek = [];

    public ?string $topRecommendation = null;

    public function mount(): void
    {
        if (! $this->monthKey) {
            $this->monthKey = CarbonImmutable::today()->format('Y-m');
        }
        $this->rebuild();
    }

    public function prevMonth(): void
    {
        $this->monthKey = CarbonImmutable::parse($this->monthKey.'-01')->subMonth()->format('Y-m');
        $this->rebuild();
    }

    public function nextMonth(): void
    {
        $this->monthKey = CarbonImmutable::parse($this->monthKey.'-01')->addMonth()->format('Y-m');
        $this->rebuild();
    }

    public function jumpToToday(): void
    {
        $this->monthKey = CarbonImmutable::today()->format('Y-m');
        $this->rebuild();
    }

    private function rebuild(): void
    {
        $monthStart = CarbonImmutable::parse($this->monthKey.'-01');
        $monthEnd = $monthStart->endOfMonth();
        $gridStart = $monthStart->startOfWeek(CarbonImmutable::SUNDAY);
        $gridEnd = $monthEnd->endOfWeek(CarbonImmutable::SATURDAY);

        $today = CarbonImmutable::today();
        $horizonStart = $today->min($gridStart);
        $horizonEnd = $today->addDays(60)->max($gridEnd);

        $accounts = Account::active()->get()->all();
        $bills = Bill::all()->all();

        $projection = (new ComputeProjectedBalance)($accounts, $horizonStart, $horizonEnd);
        $recommendations = (new RecommendPayDates)($bills, $projection, $today, $horizonEnd);

        $recByBill = [];
        foreach ($recommendations as $rec) {
            $recByBill[$rec['bill_id']] = $rec;
        }

        $this->cells = $this->buildCells($gridStart, $gridEnd, $monthStart, $bills, $recByBill, $today);
        $this->thisWeek = $this->buildThisWeek($bills, $today, $recByBill);
        $this->topRecommendation = $this->buildTopRecommendation($bills, $recByBill);
    }

    /**
     * @param  array<int, Bill>  $bills
     * @param  array<int, array{bill_id: int, recommended_date: string, warning: bool}>  $recByBill
     * @return array<int, array{day: int, in_month: bool, date: string, is_today: bool, items: array<int, array{type: string, bill_id: int, label: string}>}>
     */
    private function buildCells(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $monthStart, array $bills, array $recByBill, CarbonImmutable $today): array
    {
        $cells = [];
        $cursor = $start;
        while ($cursor->lte($end)) {
            $items = [];
            foreach ($bills as $bill) {
                if ($this->billDueOn($bill, $cursor)) {
                    $items[] = $this->pillForDue($bill, $cursor);
                }
                $rec = $recByBill[$bill->id] ?? null;
                if ($rec && $rec['recommended_date'] === $cursor->toDateString() && ! $this->billDueOn($bill, $cursor)) {
                    $items[] = ['type' => 'rec', 'bill_id' => $bill->id, 'label' => $bill->name];
                }
            }
            $cells[] = [
                'day' => $cursor->day,
                'in_month' => $cursor->month === $monthStart->month,
                'date' => $cursor->toDateString(),
                'is_today' => $cursor->isSameDay($today),
                'items' => $items,
            ];
            $cursor = $cursor->addDay();
        }

        return $cells;
    }

    private function billDueOn(Bill $bill, CarbonImmutable $date): bool
    {
        $day = min((int) $bill->due_day_of_month, $date->daysInMonth);
        if ($day !== $date->day) {
            return false;
        }
        if ($bill->cadence === 'annual') {
            return (int) $bill->due_month_of_year === $date->month;
        }

        return true;
    }

    /**
     * @return array{type: string, bill_id: int, label: string}
     */
    private function pillForDue(Bill $bill, CarbonImmutable $date): array
    {
        $period = $bill->cadence === 'annual' ? $date->format('Y') : $date->format('Y-m');
        $isPaid = in_array($period, $bill->manuallyMarkedPeriods(), true)
            || $bill->transactions()
                ->whereYear('occurred_on', $date->year)
                ->when($bill->cadence !== 'annual', fn ($q) => $q->whereMonth('occurred_on', $date->month))
                ->exists();

        if (! $isPaid) {
            return ['type' => 'due', 'bill_id' => (int) $bill->id, 'label' => $bill->name];
        }

        $tx = $bill->transactions()
            ->whereYear('occurred_on', $date->year)
            ->when($bill->cadence !== 'annual', fn ($q) => $q->whereMonth('occurred_on', $date->month))
            ->first();

        $amount = $tx ? Money::format(abs($tx->amount_cents)) : Money::format($bill->expected_amount_cents);

        return ['type' => 'paid', 'bill_id' => (int) $bill->id, 'label' => '✓ '.$bill->name.' '.$amount];
    }

    /**
     * @param  array<int, Bill>  $bills
     * @param  array<int, array{bill_id: int, recommended_date: string, warning: bool}>  $recByBill
     * @return array<int, array{bill: string, due: string, warning: bool}>
     */
    private function buildThisWeek(array $bills, CarbonImmutable $today, array $recByBill): array
    {
        $window = $today->addDays(7);
        $out = [];
        foreach ($bills as $bill) {
            $next = $bill->nextDueDate();
            if ($next->lt($today) || $next->gt($window)) {
                continue;
            }
            $rec = $recByBill[$bill->id] ?? null;
            $out[] = [
                'bill' => $bill->name,
                'due' => $next->format('D, M j'),
                'warning' => (bool) ($rec['warning'] ?? false),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, Bill>  $bills
     * @param  array<int, array{bill_id: int, recommended_date: string, warning: bool}>  $recByBill
     */
    private function buildTopRecommendation(array $bills, array $recByBill): ?string
    {
        foreach ($bills as $bill) {
            $rec = $recByBill[$bill->id] ?? null;
            if (! $rec) {
                continue;
            }
            $due = $bill->nextDueDate()->toDateString();
            if ($rec['warning']) {
                return 'Heads up — '.$bill->name.' may not be safe to pay by its due date ('.$bill->nextDueDate()->format('M j').').';
            }
            if ($rec['recommended_date'] !== $due) {
                $recDate = CarbonImmutable::parse($rec['recommended_date'])->format('M j');
                $dueDate = $bill->nextDueDate()->format('M j');

                return 'Pay '.$bill->name.' on '.$recDate.' instead of '.$dueDate.'.';
            }
        }

        return null;
    }
}; ?>

<div class="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-4">
    <div>
        <div class="flex items-center justify-between mb-3">
            <h1 class="text-2xl font-semibold">{{ \Carbon\CarbonImmutable::parse($monthKey.'-01')->format('F Y') }}</h1>
            <div class="join">
                <x-button class="join-item btn-sm btn-ghost" wire:click="prevMonth" label="‹" />
                <x-button class="join-item btn-sm btn-ghost" wire:click="jumpToToday" label="Today" />
                <x-button class="join-item btn-sm btn-ghost" wire:click="nextMonth" label="›" />
            </div>
        </div>

        <div class="grid grid-cols-7 gap-1 text-xs opacity-60 mb-1">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)
                <div class="px-2">{{ $d }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7 gap-1">
            @foreach ($cells as $cell)
                <div class="min-h-24 rounded p-1.5 {{ $cell['in_month'] ? 'bg-base-200/40' : 'bg-base-200/10 opacity-50' }} {{ $cell['is_today'] ? 'ring-2 ring-primary' : '' }}">
                    <div class="text-xs font-semibold opacity-60">{{ $cell['day'] }}</div>
                    <div class="flex flex-col gap-0.5 mt-1">
                        @foreach ($cell['items'] as $item)
                            @if ($item['type'] === 'paid')
                                <span class="badge badge-ghost opacity-50 text-[10px] truncate" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            @elseif ($item['type'] === 'rec')
                                <span class="badge badge-outline border-dashed border-primary text-primary text-[10px] truncate" title="{{ $item['label'] }} (recommended)">{{ $item['label'] }}</span>
                            @else
                                <span class="badge badge-primary text-[10px] truncate" title="{{ $item['label'] }}">{{ $item['label'] }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flex flex-col gap-3">
        <x-card title="This week">
            @if (empty($thisWeek))
                <p class="text-sm opacity-60">Nothing due in the next 7 days.</p>
            @else
                <ul class="text-sm space-y-1">
                    @foreach ($thisWeek as $row)
                        <li class="flex justify-between">
                            <span>{{ $row['bill'] }}</span>
                            <span class="opacity-60">
                                {{ $row['due'] }}
                                @if ($row['warning'])
                                    <span class="text-error">⚠</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
        <x-card title="Recommendation">
            <p class="text-sm">{{ $topRecommendation ?? 'All bills on track.' }}</p>
        </x-card>
    </div>
</div>
