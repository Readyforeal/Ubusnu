<?php

namespace App\Support;

use App\Models\Bill;

class BillMatcher
{
    /** @var array<int, array{bill_id: int, needle: string}> */
    private array $patterns = [];

    public function __construct()
    {
        $this->load();
    }

    private function load(): void
    {
        $bills = Bill::query()->whereNotNull('match_description')->get();

        foreach ($bills as $bill) {
            $needle = trim((string) $bill->match_description);
            if ($needle === '') {
                continue;
            }
            $this->patterns[] = [
                'bill_id' => $bill->id,
                'needle' => mb_strtolower($needle),
            ];
        }
    }

    public function match(string $description): ?int
    {
        $hits = [];
        $haystack = mb_strtolower($description);

        foreach ($this->patterns as $p) {
            if (str_contains($haystack, $p['needle'])) {
                $hits[$p['bill_id']] = true;
            }
        }

        return count($hits) === 1 ? (int) array_key_first($hits) : null;
    }
}
