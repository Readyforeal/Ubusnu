<?php

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;

it('renders the dashboard without JS console errors', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withStartingBalance(50000)->create(['name' => 'Test Chequing']);
    Transaction::factory()->forAccount($account)->withAmount(1000)->create();

    $this->actingAs($user);

    $page = visit(route('dashboard'));

    $page->assertSee('Test Chequing')
        ->assertNoJavascriptErrors();
})->skip('Browser harness not yet configured — run `npx playwright install` once.');
