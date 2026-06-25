<?php

use App\Models\AppSetting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('saves the Ollama base URL and model', function () {
    Livewire::test('pages::settings.coach')
        ->set('provider', 'ollama')
        ->set('ollamaBaseUrl', 'http://homelab:11434')
        ->set('ollamaModel', 'llama3.1:8b')
        ->call('save')
        ->assertHasNoErrors();

    expect(AppSetting::current()->ollama_base_url)->toBe('http://homelab:11434');
    expect(AppSetting::current()->ollama_model)->toBe('llama3.1:8b');
});
