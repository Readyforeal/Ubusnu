<?php

use App\Models\AppSetting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders with defaults', function () {
    Livewire::test('pages::settings.coach')
        ->assertSet('provider', 'gemini')
        ->assertSee('Google Gemini');
});

it('saves provider and Gemini key', function () {
    Livewire::test('pages::settings.coach')
        ->set('provider', 'gemini')
        ->set('geminiApiKey', 'AIza-key')
        ->set('coachModel', 'gemini-2.5-pro')
        ->call('save');

    $setting = AppSetting::current()->fresh();
    expect($setting->coach_provider)->toBe('gemini');
    expect($setting->gemini_api_key)->toBe('AIza-key');
    expect($setting->coach_model)->toBe('gemini-2.5-pro');
});

it('shows wipe banner when provider changes', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini']);

    Livewire::test('pages::settings.coach')
        ->set('provider', 'anthropic')
        ->set('anthropicApiKey', 'sk-ant-x')
        ->call('save')
        ->assertSet('showWipeBanner', true);
});

it('does not show wipe banner when provider unchanged', function () {
    AppSetting::current()->update(['coach_provider' => 'gemini']);

    Livewire::test('pages::settings.coach')
        ->set('geminiApiKey', 'AIza-new')
        ->call('save')
        ->assertSet('showWipeBanner', false);
});

it('switches model dropdown options when provider changes', function () {
    Livewire::test('pages::settings.coach')
        ->set('provider', 'anthropic')
        ->assertSee('Claude Sonnet')
        ->set('provider', 'gemini')
        ->assertSee('Gemini 2.5 Flash');
});
