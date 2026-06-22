<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('accounts', 'pages::accounts.index')->name('accounts.index');
    Route::livewire('accounts/{account}', 'pages::accounts.show')->name('accounts.show');

    Route::livewire('transactions', 'pages::transactions.index')->name('transactions.index');

    Route::livewire('buckets', 'pages::buckets.index')->name('buckets.index');
    Route::livewire('goals', 'pages::goals.index')->name('goals.index');

    Route::livewire('categories', 'pages::categories.index')->name('categories.index');

    Route::livewire('imports', 'pages::imports.index')->name('imports.index');
    Route::livewire('imports/new', 'pages::imports.wizard')->name('imports.new');
    Route::livewire('imports/{batch}', 'pages::imports.show')->name('imports.show');
});

require __DIR__.'/settings.php';
