<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-07-15');
    $this->actingAs(User::factory()->create());
});

afterEach(function () {
    Carbon::setTestNow();
});

it('renders the insights card with empty state when nothing to flag', function () {
    $this->get('/dashboard')->assertOk()->assertSee('Insights')->assertSee('Nothing to flag right now');
});

it('renders an insight card with a link to chat when a top mover spikes', function () {
    $account = Account::factory()->create();
    $cat = Category::factory()->create(['name' => 'Food', 'kind' => 'spending']);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-06-10', 'amount_cents' => -10000]);
    Transaction::factory()->create(['account_id' => $account->id, 'category_id' => $cat->id, 'occurred_on' => '2026-07-05', 'amount_cents' => -25000]);

    $this->get('/dashboard')
        ->assertOk()
        ->assertSee('Food spending up')
        ->assertSee('/chat?prompt=');
});
