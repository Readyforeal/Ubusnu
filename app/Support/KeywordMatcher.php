<?php

namespace App\Support;

use App\Models\Category;

class KeywordMatcher
{
    /** @var array<int, array{category_id: int, pattern: string}> */
    private array $keywords = [];

    public function __construct()
    {
        $this->load();
    }

    private function load(): void
    {
        $categories = Category::query()->whereNotNull('keywords')->get();

        foreach ($categories as $category) {
            foreach ($category->keywordList() as $keyword) {
                if ($keyword === '') {
                    continue;
                }
                $this->keywords[] = [
                    'category_id' => $category->id,
                    'pattern' => '/\b'.preg_quote($keyword, '/').'\b/iu',
                ];
            }
        }
    }

    public function match(string $description): ?int
    {
        $hits = [];

        foreach ($this->keywords as $kw) {
            if (preg_match($kw['pattern'], $description) === 1) {
                $hits[$kw['category_id']] = true;
            }
        }

        return count($hits) === 1 ? (int) array_key_first($hits) : null;
    }
}
