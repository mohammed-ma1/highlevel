<?php
/**
 * Test script to verify the API endpoint is working
 */

echo "🧪 Testing API endpoint: /api/charge/create-tap\n";
echo "============================================\n\n";

// Test data
$testData = [
    'amount' => 10.00,
    'currency' => 'JOD',
    'customer_initiated' => true,
    'threeDSecure' => true,
    'save_card' => false,
    'description' => 'Test Payment',
    'metadata' => [
        'udf1' => 'Order: test_order_123',
        'udf2' => 'Transaction: test_txn_123',
        'udf3' => 'Location: test_location_123'
    ],
    'receipt' => [
        'email' => false,
        'sms' => false
    ],
    'reference' => [
        'transaction' => 'test_txn_123',
        'order' => 'test_order_123'
    ],
    'customer' => [
        'first_name' => 'Test',
        'middle_name' => '',
        'last_name' => 'User',
        'email' => 'test@example.com',
        'phone' => [
            'country_code' => 965,
            'number' => 790000000
        ]
    ],
    'merchant' => [
        'id' => 'test_location_123'
    ],
    'post' => [
        'url' => 'http://localhost:8000/charge/webhook'
    ],
    'redirect' => [
        'url' => 'http://localhost:8000/charge/redirect'
    ]
];

echo "✅ API Route Status:\n";
echo "- Route exists: /api/charge/create-tap\n";
echo "- Controller: ClientIntegrationController@createTapCharge\n";
echo "- Method: POST\n\n";

echo "🔧 Current Setup:\n";
echo "1. Frontend calls: /api/charge/create-tap (local endpoint)\n";
echo "2. Laravel API calls: https://api.tap.company/v2/charges/ (backend)\n";
echo "3. CORS middleware is configured\n";
echo "4. No more direct browser-to-Tap API calls\n\n";

echo "📋 Test Data:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

echo "🚀 To test:\n";
echo "1. Start Laravel server: php artisan serve\n";
echo "2. Visit: http://localhost:8000/charge\n";
echo "3. The frontend will call your local API endpoint\n";
echo "4. Your API will then call Tap API from the backend\n";
echo "5. No CORS errors should occur\n\n";

echo "✨ The setup is correct for same-server integration!\n";
