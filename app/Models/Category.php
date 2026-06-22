<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'kind', 'bucket_id', 'keywords', 'color'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'kind' => 'string',
            'bucket_id' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function bucket(): BelongsTo
    {
        return $this->belongsTo(Bucket::class);
    }

    /**
     * @return array<int, string>
     */
    public function keywordList(): array
    {
        if (! $this->keywords) {
            return [];
        }

        return collect(explode(',', $this->keywords))
            ->map(fn (string $k) => trim(mb_strtolower($k)))
            ->filter()
            ->values()
            ->all();
    }
}
