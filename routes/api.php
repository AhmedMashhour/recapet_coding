<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// Authentication endpoints
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');

    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::post('/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');
        Route::get('/profile', [AuthController::class, 'profile'])->name('auth.profile');
    });
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('wallet')->group(function () {
        Route::get('/', [WalletController::class, 'show'])->name('wallet.show');
        Route::get('/balance', [WalletController::class, 'balance'])->name('wallet.balance');
        Route::get('/transactions', [WalletController::class, 'transactions'])->name('wallet.transactions');

    });

    Route::prefix('transactions')->group(function () {
        Route::post('/deposit', [TransactionController::class, 'deposit'])
            ->name('transactions.deposit');
    });


});
