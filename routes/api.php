<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

// Authentication endpoints
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register')
        ->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:auth')->name('auth.login');

    // Protected auth routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:register')
            ->name('auth.logout');
        Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:register')
            ->name('auth.refresh');
        Route::get('/profile', [AuthController::class, 'profile'])
            ->middleware('throttle:register')->name('auth.profile');
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
            ->middleware('throttle:deposits')
            ->name('transactions.deposit');

        Route::post('/withdraw', [TransactionController::class, 'withdraw'])
            ->middleware('throttle:withdrawals')
            ->name('transactions.withdraw');

        Route::post('/transfer', [TransactionController::class, 'transfer'])
            ->middleware('throttle:transfers')
            ->name('transactions.transfer');

        Route::post('/calculate-fee', [TransactionController::class, 'calculateFee'])
            ->name('transactions.calculate-fee');

        Route::get('/show', [TransactionController::class, 'show'])
            ->name('transactions.show');

    });


});


