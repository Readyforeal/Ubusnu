<?php

use App\Http\Controllers\Coach\StreamController;
use App\Http\Controllers\Coach\ThreadController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('accounts', 'pages::accounts.index')->name('accounts.index');
    Route::livewire('accounts/{account}', 'pages::accounts.show')->name('accounts.show');

    Route::livewire('transactions', 'pages::transactions.index')->name('transactions.index');

    Route::livewire('buckets', 'pages::buckets.index')->name('buckets.index');
    Route::livewire('goals', 'pages::goals.index')->name('goals.index');
    Route::livewire('bills', 'pages::bills.index')->name('bills.index');
    Route::livewire('bills/{bill}', 'pages::bills.show')->name('bills.show');

    Route::livewire('income', 'pages::income.index')->name('income.index');
    Route::livewire('income/{source}', 'pages::income.show')->name('income.show');

    Route::livewire('calendar', 'pages::calendar.index')->name('calendar.index');

    Route::livewire('categories', 'pages::categories.index')->name('categories.index');

    Route::livewire('imports', 'pages::imports.index')->name('imports.index');
    Route::livewire('imports/new', 'pages::imports.wizard')->name('imports.new');
    Route::livewire('imports/{batch}', 'pages::imports.show')->name('imports.show');

    Route::livewire('chat', 'pages::chat.index')->name('chat.index');
    Route::post('chat/threads', [ThreadController::class, 'store'])->name('chat.threads.store');
    Route::post('chat/{thread}/stream', [StreamController::class, 'stream'])->name('chat.stream');
});

require __DIR__.'/settings.php';
