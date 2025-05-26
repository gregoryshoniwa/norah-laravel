<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{any}', function () {
    return view('welcome'); // This will load the Vue.js app
})->where('any', '.*');

Route::get('/reset-password', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Zimswitch payment callback
Route::get('/payment/callback', [\App\Http\Controllers\TransactionController::class, 'handlePaymentCallback'])->name('payment.callback');
