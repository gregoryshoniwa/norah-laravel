<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\ChargesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionAuditController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\TokenController;
use App\Http\Middleware\JwtMiddleware;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//Token verification
Route::post('/validate-user-token', [TokenController::class, 'validateUserToken']);
Route::post('/validate-merchant-token', [TokenController::class, 'validateMerchantToken']);
Route::post('/validate-token', [TokenController::class, 'decodeAndValidateToken']); // Decode and validate token

// Super admin routes
Route::middleware([JwtMiddleware::class])->prefix('super')->group(function () {
    Route::get('/dashboard/stats', [SuperAdminController::class, 'getDashboardStats']);
    Route::get('/companies', [SuperAdminController::class, 'getCompanies']);
    Route::get('/merchants', [SuperAdminController::class, 'getAllMerchants']);
    Route::get('/transactions', [SuperAdminController::class, 'getAllTransactions']);
    Route::get('/users', [SuperAdminController::class, 'getSuperUsers']);
    Route::post('/users', [SuperAdminController::class, 'createSuperUser']);
    Route::put('/users/{userId}', [SuperAdminController::class, 'updateSuperUser']);
    Route::delete('/users/{userId}', [SuperAdminController::class, 'deleteSuperUser']);
});

//Charges routes
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('/system/charges/add', [ChargesController::class, 'addSystemCharge']);
    Route::get('/system/charges', [ChargesController::class, 'getAllSystemCharges']); // Get all system charges
    Route::put('/system/charges/{id}', [ChargesController::class, 'updateSystemCharge']); // Update a system charge
    Route::delete('/system/charges/{id}', [ChargesController::class, 'deleteSystemCharge']); // Delete a system charge

    Route::post('/merchant/charges/add', [ChargesController::class, 'addMerchantCharge']);
    Route::get('/merchant/charges', [ChargesController::class, 'getAllCharges']); // Get all system charges
    Route::put('/merchant/charges/{id}', [ChargesController::class, 'updateCharge']); // Update a system charge
    Route::delete('/merchant/charges/{id}', [ChargesController::class, 'deleteCharge']); // Delete a system charge
    Route::get('/merchant/charges/{merchantUserName}', [ChargesController::class, 'getChargesByMerchant']);
});


//Administrator routes

    Route::post('/auth/admin-sign-up', [AuthController::class, 'adminSignUp']);
    Route::post('/auth/resend-confirmation-email', [AuthController::class, 'resendConfirmationEmail']);
    Route::post('/auth/sign-in', [AuthController::class, 'signIn']);
    Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);
    Route::post('/auth/reauth', [AuthController::class, 'reauth']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);

    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/confirm-account', [AuthController::class, 'confirmAccount']);



// Admin routes
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('/admin/create-merchant-account', [AdminController::class, 'createMerchantAccount']);
    Route::post('/admin/secondary-admin-sign-up', [AuthController::class, 'secondaryAdminSignUp']);
    Route::get('/admin/merchants', [AdminController::class, 'getAllMerchants']); // List all merchants
    Route::put('/admin/merchants/{merchantId}/inactivate', [AdminController::class, 'inactivateMerchant']); // Inactivate a merchant
    Route::put('/admin/merchants/{merchantId}/activate', [AdminController::class, 'activateMerchant']); // Activate a merchant

    Route::get('/admin/users', [AdminController::class, 'getNonMerchantUsers']); // List all non-MERCHANT users
    Route::put('/admin/users/{userId}', [AdminController::class, 'updateUser']); // Update a user
    Route::delete('/admin/users/{userId}', [AdminController::class, 'deleteUser']); // Delete a user

    Route::get('/merchant/secret/{merchantId}', [AdminController::class, 'getMerchantSecret']); // Get merchant secret
    Route::get('/user/secret/{userId}', [AdminController::class, 'getUserSecret']); // Get user secret
});

//Transaction routes
Route::middleware([JwtMiddleware::class])->group(function () {
    Route::post('/transaction/m/generate', [TransactionController::class, 'generateMerchantTransactionToken']); // Generate merchant transaction token
    Route::post('/transaction/u/generate', [TransactionController::class, 'generateUserTransactionToken']); // Generate transaction token

    // Transaction listing routes
    Route::get('/transactions/user', [TransactionController::class, 'getUserTransactions']); // Get authenticated user's transactions
    Route::get('/transactions/merchant/{merchant_id}', [TransactionController::class, 'getMerchantTransactions']); // Get specific merchant's transactions
    Route::get('/transactions/merchant-charges/{merchant_id}', [TransactionController::class, 'getMerchantCharges']); // Get merchant's charges
    Route::get('/transactions/all', [TransactionController::class, 'getAllTransactions']); // Get all transactions (admin only)
    Route::get('/transactions/details/{id}', [TransactionController::class, 'getTransactionDetails']); // Get transaction details
    Route::get('/transactions/audits', [TransactionAuditController::class, 'index']); // Get transaction audit logs (admin/super)
    Route::post('/transactions/receipt', [TransactionController::class, 'generateReceipt'])
        ->middleware(['pdf.view'])
        ->withoutMiddleware(['web']); // Generate transaction receipt with PDF middleware

    // Dashboard routes
    Route::get('/dashboard/stats', [TransactionController::class, 'getDashboardStats']); // Get dashboard statistics
    Route::get('/dashboard/recent-transactions', [TransactionController::class, 'getRecentTransactions']); // Get recent transactions with search and pagination

    // Settings routes
    Route::get('/settings', [SettingsController::class, 'getSettings']); // Get user settings
    Route::post('/settings', [SettingsController::class, 'saveSettings']); // Save user settings
    Route::delete('/settings/reset', [SettingsController::class, 'resetSettings']); // Reset user settings to defaults

    // Self-service company profile + bank details
    Route::get('/profile', [\App\Http\Controllers\CompanyProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\CompanyProfileController::class, 'update']);
    Route::put('/profile/bank', [\App\Http\Controllers\CompanyProfileController::class, 'updateBank']);
    Route::get('/super/profile/{userId}', [\App\Http\Controllers\CompanyProfileController::class, 'superShow'])->whereNumber('userId');
    Route::put('/super/profile/{userId}', [\App\Http\Controllers\CompanyProfileController::class, 'superUpdate'])->whereNumber('userId');

    // Payout self-service (ADMIN / MERCHANT)
    Route::get('/payouts/expected', [\App\Http\Controllers\PayoutController::class, 'expected']);
    Route::get('/payouts', [\App\Http\Controllers\PayoutController::class, 'index']);
    Route::get('/payouts/{id}', [\App\Http\Controllers\PayoutController::class, 'show'])->whereNumber('id');
    Route::post('/payouts/{id}/confirm', [\App\Http\Controllers\PayoutController::class, 'confirm'])->whereNumber('id');
    Route::post('/payouts/{id}/dispute', [\App\Http\Controllers\PayoutController::class, 'dispute'])->whereNumber('id');
    Route::get('/payouts/messages', [\App\Http\Controllers\PayoutController::class, 'listMessages']);
    Route::post('/payouts/messages', [\App\Http\Controllers\PayoutController::class, 'createMessage']);
    Route::get('/payouts/messages/{id}', [\App\Http\Controllers\PayoutController::class, 'showThread'])->whereNumber('id');
    Route::post('/payouts/messages/{id}/reply', [\App\Http\Controllers\PayoutController::class, 'reply'])->whereNumber('id');

    // Super-admin payouts
    Route::get('/super/payouts/outstanding', [\App\Http\Controllers\SuperPayoutController::class, 'outstanding']);
    Route::get('/super/payouts/profit-loss', [\App\Http\Controllers\SuperPayoutController::class, 'profitLoss']);
    Route::get('/super/payouts/messages', [\App\Http\Controllers\SuperPayoutController::class, 'inbox']);
    Route::get('/super/payouts/messages/{id}', [\App\Http\Controllers\SuperPayoutController::class, 'thread'])->whereNumber('id');
    Route::post('/super/payouts/messages/{id}/reply', [\App\Http\Controllers\SuperPayoutController::class, 'reply'])->whereNumber('id');
    Route::get('/super/payouts', [\App\Http\Controllers\SuperPayoutController::class, 'index']);
    Route::post('/super/payouts', [\App\Http\Controllers\SuperPayoutController::class, 'store']);
    Route::get('/super/payouts/{id}', [\App\Http\Controllers\SuperPayoutController::class, 'show'])->whereNumber('id');
    Route::post('/super/payouts/{id}/send', [\App\Http\Controllers\SuperPayoutController::class, 'send'])->whereNumber('id');

    // Auto-scheduled payouts
    Route::get('/super/payout-schedules', [\App\Http\Controllers\SuperPayoutController::class, 'listSchedules']);
    Route::post('/super/payout-schedules', [\App\Http\Controllers\SuperPayoutController::class, 'storeSchedule']);
    Route::put('/super/payout-schedules/{id}', [\App\Http\Controllers\SuperPayoutController::class, 'updateSchedule'])->whereNumber('id');
    Route::delete('/super/payout-schedules/{id}', [\App\Http\Controllers\SuperPayoutController::class, 'deleteSchedule'])->whereNumber('id');
    Route::post('/super/payout-schedules/{id}/run-now', [\App\Http\Controllers\SuperPayoutController::class, 'runScheduleNow'])->whereNumber('id');
});
Route::post('/transactions/confirmation', [TransactionController::class, 'confirmTransaction']);
Route::post('/transactions/omari-otp', [TransactionController::class, 'processOmariOtp']);
Route::post('/transactions/process', [TransactionController::class, 'processTransaction']);
Route::post('/transactions/cancel', [TransactionController::class, 'cancelTransaction']);
Route::post('/transactions/status', [TransactionController::class, 'checkTransactionStatus']);
Route::post('/zimswitch/payment-status', [TransactionController::class, 'checkZimswitchPaymentStatus']);
Route::post('/zimswitch/test-checkout', [TransactionController::class, 'testZimswitchCheckout']);
Route::post('/zimswitch/handle-eftpay-callback', [TransactionController::class, 'handleEftPayCallback']);
Route::post('/transactions/zimswitch-finalize', [TransactionController::class, 'finalizeZimswitchPayment']);
Route::post('/test-charge-calculation', [TransactionController::class, 'testChargeCalculation']);


Route::post('/merchant-sign-in', [AuthController::class, 'merchantSignIn']);

// // Protected routes
// Route::middleware('auth:sanctum')->group(function () {
//     Route::post('/logout', [AuthController::class, 'logout']);
//     Route::get('/user', function (Request $request) {
//         return $request->user();
//     });
// });
