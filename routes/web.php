<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Zimswitch payment callback
Route::get('/payment/callback', [\App\Http\Controllers\TransactionController::class, 'handlePaymentCallback'])->name('payment.callback');

// iVeri (VISA/MasterCard) 3D Secure callback - rendered inside the popup.
Route::match(['get', 'post'], '/payment/iveri/callback', [\App\Http\Controllers\TransactionController::class, 'handleIveriCallback'])->name('payment.iveri.callback');

// EcoCash provider-side notify - they post their raw payload here, NOT to the
// merchant's webhook URL. The merchant only gets our wrapped sendStatusWebhook.
Route::match(['get', 'post'], '/payment/ecocash/notify', [\App\Http\Controllers\TransactionController::class, 'handleEcocashNotify'])->name('payment.ecocash.notify');

// Payment result pages
Route::get('/payment/success', function () {
    $reference = request('reference');
    return view('payment.success', compact('reference'));
})->name('payment.success');

Route::get('/payment/error', function () {
    $message = request('message', 'An error occurred processing your payment');
    return view('payment.error', compact('message'));
})->name('payment.error');

Route::get('/payment/failed', function () {
    $reference = request('reference');
    return view('payment.error', [
        'message' => 'Payment failed' . ($reference ? " (reference: {$reference})" : ''),
    ]);
})->name('payment.failed');

Route::get('/{any}', function () {
    return view('welcome'); // This will load the Vue.js app
})->where('any', '.*');
