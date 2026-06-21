<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class TransferCategorySeeder extends Seeder
{
    public function run(): void
    {
        Category::updateOrCreate(
            ['name' => 'Transfer'],
            [
                'keywords' => 'transfer, tfr, to chequing, to savings, e-transfer, etfr',
                'excluded_from_totals' => true,
            ]
        );
    }
}
