
<?php

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
    $publishableKey = $user ? $user->lead_test_publishable_key : 'pk_test_xItqaSsJzl5g2K08fCwYbMvQ';
    $merchantId = '61000786'; // You can get this from user config as well
    
    return view('tap', compact('publishableKey', 'merchantId'));
})->name('tap');

Route::post('/webhook', [ClientIntegrationController::class, 'webhook'])
    ->name('client.webhook');

Route::post('/provider/connect-or-disconnect', [ClientIntegrationController::class, 'connectOrDisconnect'])
    ->name('provider.connect_or_disconnect');

// Payment query endpoint for GoHighLevel
Route::post('/payment/query', [PaymentQueryController::class, 'handleQuery'])
    ->name('payment.query');

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
