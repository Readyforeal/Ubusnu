<?php

use App\Models\Account;
use App\Models\User;

it('walks through the full import wizard end to end', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['name' => 'Test Chequing']);

    $this->actingAs($user);

    $page = visit(route('imports.new'));

    $page->assertSee('Upload')
        ->assertSee('Map columns')
        ->assertSee('Preview');

    $page->select('#accountId', (string) $account->id);

    $page->attach('input[type=file]', base_path('tests/Fixtures/csv/sample-standard.csv'));

    $page->click('Next');

    $page->assertSee('Detected headers');

    $page->select('#mapDateColumn', 'Date')
        ->fill('#mapDateFormat', 'm/d/Y')
        ->select('#mapDescriptionColumn', 'Description')
        ->select('#mapAmountColumn', 'Amount')
        ->click('Next');

    $page->assertSee('new');

    $page->click('Import')
        ->assertSee('Import complete');
})->skip('Selectors may need tuning to match MaryUI rendered HTML — enable when wizard UI is settled.');
