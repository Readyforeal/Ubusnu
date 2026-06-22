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
                'kind' => 'transfer',
                'keywords' => 'transfer, tfr, to chequing, to savings, e-transfer, etfr',
            ]
        );
    }
}
