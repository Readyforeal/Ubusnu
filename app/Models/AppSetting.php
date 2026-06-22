<?php

namespace App\Models;

use Database\Factories\AppSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable(['monthly_income_target_cents', 'forecast_lookback_weeks'])]
class AppSetting extends Model
{
    /** @use HasFactory<AppSettingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'monthly_income_target_cents' => 'integer',
            'forecast_lookback_weeks' => 'integer',
        ];
    }

    public static function current(): self
    {
        $setting = static::find(1);

        if ($setting) {
            return $setting;
        }

        DB::table('app_settings')->insert([
            'id' => 1,
            'monthly_income_target_cents' => 0,
            'forecast_lookback_weeks' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return static::find(1);
    }
}
