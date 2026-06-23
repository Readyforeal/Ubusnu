<?php

namespace App\Actions\Finance\Analytics;

use App\Models\Bill;
use App\Models\Transaction;
use Carbon\CarbonImmutable;

class DetectRecurringSubscriptions
{
    /**
     * @return array<int, array{merchant_pattern: string, occurrence_count: int, monthly_avg_cents: int, last_seen_on: string, already_tracked_as_bill_id: ?int}>
     */
    public function __invoke(): array
    {
        $today = CarbonImmutable::today();
        $start = $today->subMonthsNoOverflow(6);

        $rows = Transaction::query()
            ->whereDate('occurred_on', '>=', $start->toDateString())
            ->whereDate('occurred_on', '<=', $today->toDateString())
            ->where('amount_cents', '<', 0)
            ->whereNull('deleted_at')
            ->whereNull('bill_id')
            ->get(['description', 'amount_cents', 'occurred_on']);

        $groups = [];
        foreach ($rows as $row) {
            $token = $this->firstSignificantToken((string) $row->description);
            if ($token === '') {
                continue;
            }
            $key = $token.'|'.abs((int) $row->amount_cents);
            $groups[$key]['token'] = $token;
            $groups[$key]['amount'] = abs((int) $row->amount_cents);
            $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
            $groups[$key]['last'] = max($groups[$key]['last'] ?? '0000-00-00', substr((string) $row->occurred_on, 0, 10));
        }

        $billMatches = Bill::query()->whereNotNull('match_description')->get(['id', 'match_description'])->all();

        $out = [];
        foreach ($groups as $g) {
            if ($g['count'] < 3) {
                continue;
            }
            $matchedBillId = null;
            foreach ($billMatches as $b) {
                if (str_contains(strtoupper($g['token']), strtoupper((string) $b->match_description))) {
                    $matchedBillId = (int) $b->id;
                    break;
                }
            }
            $out[] = [
                'merchant_pattern' => $g['token'],
                'occurrence_count' => (int) $g['count'],
                'monthly_avg_cents' => (int) $g['amount'],
                'last_seen_on' => (string) $g['last'],
                'already_tracked_as_bill_id' => $matchedBillId,
            ];
        }

        usort($out, fn ($a, $b) => $b['occurrence_count'] <=> $a['occurrence_count']);

        return $out;
    }

    private function firstSignificantToken(string $description): string
    {
        $clean = preg_replace('/[^A-Z0-9 \.]/i', ' ', strtoupper($description));
        $tokens = preg_split('/\s+/', trim((string) $clean));

        return $tokens[0] ?? '';
    }
}
