<?php

use App\Livewire\Accounts\Index as AccountsIndex;
use App\Livewire\Accounts\Show as AccountShow;
use App\Livewire\Categories\Index as CategoriesIndex;
use App\Livewire\Imports\Index as ImportsIndex;
use App\Livewire\Imports\Wizard as ImportsWizard;
use App\Livewire\Transactions\Index as TransactionsIndex;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('accounts', AccountsIndex::class)->name('accounts.index');
    Route::get('accounts/{account}', AccountShow::class)->name('accounts.show');

    Route::get('transactions', TransactionsIndex::class)->name('transactions.index');

    Route::get('categories', CategoriesIndex::class)->name('categories.index');

    Route::get('imports', ImportsIndex::class)->name('imports.index');
    Route::get('imports/new', ImportsWizard::class)->name('imports.new');
});

require __DIR__.'/settings.php';
