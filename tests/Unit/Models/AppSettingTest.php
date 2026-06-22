<?php

use App\Models\AppSetting;

it('current() returns the singleton row', function () {
    $setting = AppSetting::current();

    expect($setting->id)->toBe(1);
    expect($setting->monthly_income_target_cents)->toBe(0);
});

it('current() returns the same row across calls', function () {
    $a = AppSetting::current();
    $b = AppSetting::current();

    expect($a->id)->toBe($b->id);
});

it('current() creates the singleton if missing', function () {
    AppSetting::query()->delete();

    $setting = AppSetting::current();

    expect($setting->id)->toBe(1);
    expect(AppSetting::count())->toBe(1);
});

it('persists monthly_income_target_cents updates', function () {
    $setting = AppSetting::current();
    $setting->update(['monthly_income_target_cents' => 500000]);

    expect(AppSetting::current()->monthly_income_target_cents)->toBe(500000);
});
