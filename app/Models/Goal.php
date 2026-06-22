<?php

namespace App\Models;

use Database\Factories\GoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'target_cents', 'priority_percentage', 'color', 'notes', 'sort_order'])]
class Goal extends Model
{
    /** @use HasFactory<GoalFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'target_cents' => 'integer',
            'priority_percentage' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
