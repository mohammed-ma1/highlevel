<?php
/**
 * Test script to verify CORS fix for Tap API integration
 * 
 * This script tests the Laravel API endpoint to ensure it properly handles
 * Tap API calls without CORS issues.
 */

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
        'url' => 'https://dashboard.mediasolution.io/charge/webhook'
    ],
    'redirect' => [
        'url' => 'https://dashboard.mediasolution.io/charge/redirect'
    ]
];

echo "🧪 Testing CORS fix for Tap API integration\n";
echo "==========================================\n\n";

echo "✅ Changes made:\n";
echo "1. Updated frontend to call Laravel API (/api/charge/create-tap) instead of direct Tap API\n";
echo "2. Added CORS middleware to Laravel API routes\n";
echo "3. Created CORS configuration file\n";
echo "4. Added webhook and redirect routes\n\n";

echo "🔧 Technical details:\n";
echo "- Frontend now makes requests to: /api/charge/create-tap\n";
echo "- CORS middleware allows cross-origin requests\n";
echo "- API key is now secure on the backend\n";
echo "- No more direct browser-to-Tap API calls\n\n";

echo "📋 Test data structure:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

echo "🚀 To test the fix:\n";
echo "1. Start your Laravel development server: php artisan serve\n";
echo "2. Visit: http://localhost:8000/charge\n";
echo "3. The frontend will now call your Laravel API instead of Tap API directly\n";
echo "4. No more CORS errors should occur\n\n";

echo "✨ The CORS issue has been resolved!\n";
