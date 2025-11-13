
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClientIntegrationController;
use App\Http\Controllers\LeadConnectorPaymentController;
use App\Http\Controllers\PaymentQueryController;

Route::get('/connect', [ClientIntegrationController::class, 'connect'])
    ->name('client.connect');

Route::get('/landing', function () {
    return view('welcome');
})->name('welcome');

Route::get('/test', function () {
    return view('test');
})->name('test');

Route::get('/tap', function () {
    // Get the first user's publishable key for demo purposes
    $user = \App\Models\User::whereNotNull('lead_test_publishable_key')->first();
    $publishableKey = $user ? $user->lead_test_publishable_key : 'pk_test_YhUjg9PNT8oDlKJ1aE2fMRz7';
    $merchantId = 'merchant_id_here'; // You can get this from user config as well

    
    return view('tap', compact('publishableKey', 'merchantId'));
})->name('tap');

Route::get('/charge', function () {
    return view('charge');
})->name('charge');

// GoHighLevel payment verification endpoint
Route::post('/payment/verify', [ClientIntegrationController::class, 'verifyPayment'])
    ->name('payment.verify');

Route::post('/webhook', [ClientIntegrationController::class, 'webhook'])
    ->name('client.webhook');

Route::post('/provider/connect-or-disconnect', [ClientIntegrationController::class, 'connectOrDisconnect'])
    ->name('provider.connect_or_disconnect');

// Payment query endpoint moved to API routes to avoid CSRF issues

// Test route for payment query
Route::get('/test-payment-query', function () {
    $testData = [
        'type' => 'verify',
        'locationId' => 'test_location_123',
        'apiKey' => 'test_api_key',
        'transactionId' => 'test_transaction_123'
    ];
    
    $request = new \Illuminate\Http\Request($testData);
    $controller = new \App\Http\Controllers\PaymentQueryController();
    
    return $controller->handleQuery($request);
})->name('test.payment.query');

// New Charge API routes
Route::post('/charge/create', [ClientIntegrationController::class, 'createCharge'])
    ->name('charge.create');

// Charge status retrieval endpoint
Route::get('/charge/status', [ClientIntegrationController::class, 'getChargeStatus'])
    ->name('charge.status');

// Test route for charge status (for debugging)
Route::get('/test-charge-status/{tapId}', function ($tapId) {
    $request = new \Illuminate\Http\Request(['tap_id' => $tapId]);
    $controller = new \App\Http\Controllers\ClientIntegrationController();
    
    return $controller->getChargeStatus($request);
})->name('test.charge.status');


Route::get('/payment/redirect', function () {
    return view('payment.redirect');
})->name('payment.redirect');

// Webhook route for Tap charge completion
Route::post('/charge/webhook', function (Request $request) {
    \Log::info('Tap webhook received', ['data' => $request->all()]);
    return response()->json(['status' => 'success']);
})->name('charge.webhook');

// Redirect route for Tap charge completion
Route::get('/charge/redirect', function (Request $request) {
    \Log::info('Tap redirect received', ['data' => $request->all()]);
    return view('payment.redirect', ['data' => $request->all()]);
})->name('charge.redirect');
