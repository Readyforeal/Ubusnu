<?php

namespace App\Support;

use App\Models\IncomeSource;

class IncomeMatcher
{
    /** @var array<int, array{income_source_id: int, needle: string}> */
    private array $patterns = [];

    public function __construct()
    {
        $this->load();
    }

    private function load(): void
    {
        $sources = IncomeSource::query()->whereNotNull('match_description')->get();

        foreach ($sources as $source) {
            $needle = trim((string) $source->match_description);
            if ($needle === '') {
                continue;
            }
            $this->patterns[] = [
                'income_source_id' => $source->id,
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
                $hits[$p['income_source_id']] = true;
            }
        }

        return count($hits) === 1 ? (int) array_key_first($hits) : null;
    }
}
