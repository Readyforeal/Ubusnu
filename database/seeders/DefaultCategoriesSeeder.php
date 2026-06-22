<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class DefaultCategoriesSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, kind: string, keywords: string}>
     */
    private array $categories = [
        ['name' => 'Fast Food & Dining', 'kind' => 'spending', 'keywords' => 'MCDONALD,RUNZA,TACO BELL,PIZZA HUT,KFC,ARBYS,SONIC,DAVES HOT CHICKEN,CRACKER BARREL,COUSINS MAINE,INNER RAIL,BEANERY'],
        ['name' => 'Coffee', 'kind' => 'spending', 'keywords' => 'STARBUCKS,SCOOTER'],
        ['name' => 'Gas & Fuel', 'kind' => 'spending', 'keywords' => 'KONECKY,CASEYS,PUMP AND PANTRY,U-STOP,MEGA SAVER,R & K COUNTRY STORE'],
        ['name' => 'Groceries & Big Box Retail', 'kind' => 'spending', 'keywords' => 'WALMART,WAL-MART,WM SUPERCENTER,TARGET,T.J. MAXX,TJ MAXX,ROSS,FIVE BELOW'],
        ['name' => 'Online Shopping (Amazon)', 'kind' => 'spending', 'keywords' => 'AMAZON,AMZN'],
        ['name' => 'Home Improvement & Hobby', 'kind' => 'spending', 'keywords' => "LOWES,LOWE'S,MENARDS,MNRD,WOODCRAFT,CRICUT,ZURCHERS"],
        ['name' => 'Subscriptions & Digital Services', 'kind' => 'spending', 'keywords' => 'APPLE.COM,APPLE CASH,WALMART+,AMAZON PRIME,PETCUBE,NATIVE INSTRUMEN,NI*,SP 4GVN,4GVN'],
        ['name' => 'Utilities', 'kind' => 'spending', 'keywords' => 'BLACK HILLS,SPECTRUM,T-MOBILE,VILLAGE OF MEAD,WASTE CONNECTION'],
        ['name' => 'Insurance', 'kind' => 'spending', 'keywords' => 'PROG NORTHERN,PROGRESSIVE,ALLSTATE,AMERICAN STRATEG'],
        ['name' => 'Mortgage & Loans', 'kind' => 'spending', 'keywords' => 'US BANK HOME MTG,HOME MTG,AFFIRM,SYF-PAYLTR,1ST NATL BK,FNBO'],
        ['name' => 'Credit Card Payments', 'kind' => 'spending', 'keywords' => 'CHASE CREDIT,CREDIT CRD'],
        ['name' => 'Income & Deposits', 'kind' => 'income', 'keywords' => 'PAYROLL,ALLAN MICHAEL,MOBILE DEPOSIT'],
        ['name' => 'Transfers', 'kind' => 'transfer', 'keywords' => 'ONLINE TRANSFER,XFR TO SAV,FNBO XFR'],
        ['name' => 'Person-to-Person', 'kind' => 'spending', 'keywords' => 'VENMO,APPLE CASH SENT'],
        ['name' => 'Giving / Church', 'kind' => 'spending', 'keywords' => 'REVIVALOMAHA'],
        ['name' => 'Recreation & Misc', 'kind' => 'spending', 'keywords' => 'FONTENELLE FOREST,PUBLICRECORDS'],
    ];

    public function run(): void
    {
        foreach ($this->categories as $category) {
            Category::updateOrCreate(
                ['name' => $category['name']],
                [
                    'kind' => $category['kind'],
                    'keywords' => $category['keywords'],
                ]
            );
        }
    }
}
