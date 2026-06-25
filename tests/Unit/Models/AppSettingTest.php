<?php

use App\Models\AppSetting;
use Illuminate\Support\Facades\DB;

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

it('defaults coach_provider to gemini', function () {
    expect(AppSetting::current()->coach_provider)->toBe('gemini');
});

it('persists coach_model and reads it back', function () {
    AppSetting::current()->update(['coach_model' => 'gemini-2.5-pro']);

    expect(AppSetting::current()->fresh()->coach_model)->toBe('gemini-2.5-pro');
});

it('encrypts gemini_api_key at rest', function () {
    AppSetting::current()->update(['gemini_api_key' => 'AIza-secret-test-key']);

    expect(AppSetting::current()->fresh()->gemini_api_key)->toBe('AIza-secret-test-key');

    $raw = DB::table('app_settings')->where('id', 1)->value('gemini_api_key');
    expect($raw)->not->toBeNull();
    expect($raw)->not->toContain('AIza-secret-test-key');
});

it('encrypts anthropic_api_key at rest', function () {
    AppSetting::current()->update(['anthropic_api_key' => 'sk-ant-secret-test-key']);

    expect(AppSetting::current()->fresh()->anthropic_api_key)->toBe('sk-ant-secret-test-key');

    $raw = DB::table('app_settings')->where('id', 1)->value('anthropic_api_key');
    expect($raw)->not->toContain('sk-ant-secret-test-key');
});
