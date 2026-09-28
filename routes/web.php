<?php

use App\Http\Controllers\BankAccountController;
use App\Http\Controllers\MoneyOperationController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/accounts')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', '/accounts')->name('dashboard');
    Route::get('/accounts', [BankAccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [BankAccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}', [BankAccountController::class, 'show'])->name('accounts.show');
    Route::delete('/accounts/{account}', [BankAccountController::class, 'destroy'])->name('accounts.destroy');
    Route::post('/accounts/{account}/deposits', MoneyOperationController::class)->name('accounts.deposits');
    Route::post('/accounts/{account}/withdrawals', MoneyOperationController::class)->name('accounts.withdrawals');
    Route::post('/accounts/{account}/loan', MoneyOperationController::class)->name('accounts.loan');
    Route::post('/transfers', MoneyOperationController::class)->name('transfers.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
