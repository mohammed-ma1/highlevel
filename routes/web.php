
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
})->name('charge')->middleware('payment.policy');

// GoHighLevel payment verification endpoint
Route::post('/payment/verify', [ClientIntegrationController::class, 'verifyPayment'])
    ->name('payment.verify');

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
})->name('payment.redirect')->middleware('payment.policy');

// Webhook route for Tap charge completion
Route::post('/charge/webhook', function (Request $request) {
    \Log::info('Tap webhook received', ['data' => $request->all()]);
    return response()->json(['status' => 'success']);
})->name('charge.webhook');

// Redirect route for Tap charge completion
Route::get('/charge/redirect', function (Request $request) {
    \Log::info('Tap redirect received', ['data' => $request->all()]);
    return view('payment.redirect', ['data' => $request->all()]);
})->name('charge.redirect')->middleware('payment.policy');

// Safari iframe workaround route - creates charge outside iframe context
Route::get('/charge/safari-redirect', function (Request $request) {
    \Log::info('Safari redirect workaround', ['data' => $request->all()]);
    
    $amount = $request->get('amount');
    $currency = $request->get('currency', 'JOD');
    $orderId = $request->get('orderId');
    $transactionId = $request->get('transactionId');
    $locationId = $request->get('locationId');
    $customerData = $request->get('customer');
    
    // Parse customer data if provided
    $customer = null;
    if ($customerData) {
        try {
            $customer = json_decode($customerData, true);
        } catch (\Exception $e) {
            \Log::warning('Safari redirect: Failed to parse customer data', ['error' => $e->getMessage()]);
        }
    }
    
    // Find user by location ID
    $user = \App\Models\User::where('lead_location_id', $locationId)->first();
    if (!$user) {
        \Log::error('Safari redirect: User not found for locationId', ['locationId' => $locationId]);
        return view('payment.error', ['message' => 'User not found for location: ' . $locationId]);
    }
    
    \Log::info('Safari redirect: User found', ['userId' => $user->id, 'locationId' => $locationId]);
    
    // Get API keys from user - use the same logic as the main controller
    $isLive = $user->lead_tap_mode === 'live';
    $apiKey = $isLive ? $user->lead_live_api_key : $user->lead_test_api_key;
    $publishableKey = $isLive ? $user->lead_live_publishable_key : $user->lead_test_publishable_key;
    
    \Log::info('Safari redirect: API keys', [
        'isLive' => $isLive,
        'hasApiKey' => !empty($apiKey),
        'hasPublishableKey' => !empty($publishableKey),
        'apiKeyPrefix' => $apiKey ? substr($apiKey, 0, 20) . '...' : 'none'
    ]);
    
    if (!$apiKey || !$publishableKey) {
        \Log::error('Safari redirect: API keys not configured', [
            'hasApiKey' => !empty($apiKey),
            'hasPublishableKey' => !empty($publishableKey),
            'isLive' => $isLive
        ]);
        return view('payment.error', ['message' => 'API keys not configured for this location']);
    }
    
    // Override the redirect URLs to use production domain
    $productionUrl = 'https://dashboard.mediasolution.io';
    
    // Create a custom payload with production URLs and customer data
    $customPayload = [
        'amount' => $amount,
        'currency' => $currency,
        'threeDSecure' => true,
        'save_card' => false,
        'customer_initiated' => true,
        'description' => 'Payment via GoHighLevel Integration',
        'statement_descriptor' => 'GHL Payment',
        'source' => ['id' => 'src_all'],
        'redirect' => ['url' => $productionUrl . '/charge/redirect'],
        'post' => ['url' => $productionUrl . '/charge/webhook'],
        'reference' => [
            'order' => $orderId,
            'transaction' => $transactionId
        ],
        'receipt' => ['email' => true, 'sms' => false],
        'merchant' => ['id' => $locationId]
    ];
    
    // Add customer data if available
    if ($customer && isset($customer['name']) && isset($customer['email'])) {
        $customerName = $customer['name'];
        $nameParts = explode(' ', $customerName, 2);
        
        $customPayload['customer'] = [
            'first_name' => $nameParts[0] ?? 'Customer',
            'last_name' => $nameParts[1] ?? 'User',
            'email' => $customer['email'] ?? 'customer@example.com',
            'phone' => [
                'country_code' => '965',
                'number' => $customer['contact'] ?? '790000000'
            ]
        ];
    }
    
    \Log::info('Safari redirect: Creating charge with payload', ['payload' => $customPayload]);
    
    // Make direct API call with production URLs
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'Authorization' => 'Bearer ' . $apiKey,
        'accept' => 'application/json',
        'content-type' => 'application/json'
    ])->withBody(json_encode($customPayload), 'application/json')
      ->post('https://api.tap.company/v2/charges/');
    
    if ($response->successful()) {
        $chargeResponse = $response->json();
        \Log::info('Safari redirect: Charge created successfully', ['chargeId' => $chargeResponse['id'] ?? 'unknown']);
        
        // Redirect to Tap checkout URL
        if (isset($chargeResponse['transaction']['url'])) {
            return redirect($chargeResponse['transaction']['url']);
        } else {
            \Log::error('Safari redirect: No checkout URL in response', ['response' => $chargeResponse]);
            return view('payment.error', ['message' => 'No checkout URL received from Tap']);
        }
    } else {
        \Log::error('Safari redirect: Failed to create charge', [
            'status' => $response->status(),
            'response' => $response->json()
        ]);
        return view('payment.error', ['message' => 'Failed to create charge with Tap: ' . ($response->json()['errors'][0]['description'] ?? 'Unknown error')]);
    }
})->name('charge.safari-redirect')->middleware('payment.policy');
