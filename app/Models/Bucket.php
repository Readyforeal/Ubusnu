<?php

namespace App\Models;

use Database\Factories\BucketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'target_percentage', 'color', 'sort_order'])]
class Bucket extends Model
{
    /** @use HasFactory<BucketFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'target_percentage' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function targetCents(int $incomeTargetCents): int
    {
        return intdiv($incomeTargetCents * $this->target_percentage, 100);
    }
}
