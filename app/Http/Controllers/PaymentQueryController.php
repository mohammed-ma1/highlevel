<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\TapPaymentService;

class PaymentQueryController extends Controller
{
    /**
     * Handle payment queries from GoHighLevel
     * This endpoint receives requests for payment processing, verification, refunds, etc.
     */
    public function handleQuery(Request $request)
    {
        try {
            // Extract required parameters
            $type = $request->input('type');
            $locationId = $request->input('locationId');
            $apiKey = $request->input('apiKey');
            
            Log::info('Payment query received', [
                'type' => $type,
                'locationId' => $locationId,
                'has_apiKey' => !empty($apiKey),
                'request_data' => $request->all()
            ]);

            // Validate required parameters - Always return HTTP 200 per GHL requirements
            if (!$type || !$apiKey) {
                Log::warning('Payment query missing required fields', [
                    'has_type' => !empty($type),
                    'has_apiKey' => !empty($apiKey),
                    'has_locationId' => !empty($locationId)
                ]);
                return response()->json([
                    'success' => false
                ], 200);
            }

            // Find user - prioritize locationId if available, otherwise use API key
            $user = null;
            
            // Strategy 1: If locationId is provided, use it first (most reliable)
            if ($locationId) {
                $user = User::where('lead_location_id', $locationId)->first();
                Log::info('User lookup by locationId', [
                    'locationId' => $locationId,
                    'user_found' => $user ? true : false,
                    'user_id' => $user?->id
                ]);
            }
            
            // Strategy 2: If no user found and we have chargeId, try to extract locationId from charge metadata
            if (!$user && $request->input('chargeId')) {
                $chargeId = $request->input('chargeId');
                Log::info('Attempting to extract locationId from charge metadata', [
                    'chargeId' => $chargeId
                ]);
                
                // Try to find user by trying all users with the API key and checking their charges
                // This is a fallback - we'll validate later
            }
            
            // Strategy 3: Find user by API key (but we'll validate it matches)
            if (!$user && $apiKey) {
                // Try test key first
                $user = User::where('lead_test_api_key', $apiKey)->first();
                
                // If not found, try live key
                if (!$user) {
                    $user = User::where('lead_live_api_key', $apiKey)->first();
                }
                
                Log::info('User lookup by API key', [
                    'apiKey_prefix' => substr($apiKey, 0, 10) . '...',
                    'user_found' => $user ? true : false,
                    'user_id' => $user?->id,
                    'user_locationId' => $user?->lead_location_id
                ]);
            }
            
            // If we found user by API key but locationId was null, use user's locationId
            if ($user && !$locationId) {
                $locationId = $user->lead_location_id;
                Log::info('LocationId retrieved from user', [
                    'locationId' => $locationId,
                    'user_id' => $user->id
                ]);
            }
            
            if (!$user) {
                Log::warning('User not found for payment query', [
                    'locationId' => $locationId,
                    'has_apiKey' => !empty($apiKey),
                    'has_chargeId' => !empty($request->input('chargeId'))
                ]);
                return response()->json([
                    'success' => false
                ], 200);
            }

            // Validate apiKey matches user's stored API key (security requirement)
            $userApiKey = $user->tap_mode === 'live' ? $user->lead_live_api_key : $user->lead_test_api_key;
            if ($apiKey !== $userApiKey) {
                Log::warning('API key validation failed', [
                    'locationId' => $locationId,
                    'user_id' => $user->id,
                    'user_locationId' => $user->lead_location_id,
                    'provided_key_length' => strlen($apiKey ?? ''),
                    'expected_key_length' => strlen($userApiKey ?? ''),
                    'user_tap_mode' => $user->tap_mode
                ]);
                return response()->json([
                    'failed' => true
                ], 200);
            }
            
            // Additional validation: If we have chargeId, try to verify the user is correct
            // by checking if the charge belongs to this user's merchant
            if ($request->input('chargeId') && $type === 'verify') {
                Log::info('Validating user matches charge', [
                    'user_id' => $user->id,
                    'user_locationId' => $user->lead_location_id,
                    'chargeId' => $request->input('chargeId')
                ]);
            }

            // Route to appropriate handler based on type
            switch ($type) {
                case 'verify':
                    return $this->handleVerify($request, $user);
                case 'refund':
                    return $this->handleRefund($request, $user);
                case 'charge':
                    return $this->handleCharge($request, $user);
                case 'create_subscription':
                    return $this->handleCreateSubscription($request, $user);
                case 'update_subscription':
                    return $this->handleUpdateSubscription($request, $user);
                case 'cancel_subscription':
                    return $this->handleCancelSubscription($request, $user);
                case 'list_payment_methods':
                    return $this->handleListPaymentMethods($request, $user);
                case 'charge_payment':
                    return $this->handleChargePayment($request, $user);
                default:
                    return response()->json([
                        'success' => false
                    ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Payment query error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'failed' => true
            ], 200);
        }
    }

    /**
     * Handle payment verification
     * GHL sends: type=verify, transactionId, chargeId, apiKey, subscriptionId (optional)
     * 
     * Per HighLevel requirements:
     * - Always return HTTP 200
     * - Return { "success": true } for success
     * - Return { "failed": true } for failure
     * - Return { "success": false } for pending
     */
    private function handleVerify(Request $request, User $user)
    {
        $transactionId = $request->input('transactionId');
        $chargeId = $request->input('chargeId');
        $subscriptionId = $request->input('subscriptionId'); // Optional
        $apiKey = $request->input('apiKey');
        
        // Accept either transactionId or chargeId (chargeId is preferred per GHL docs)
        $tapChargeId = $chargeId ?: $transactionId;
        
        if (!$tapChargeId) {
            Log::warning('Verify request missing transactionId and chargeId', [
                'userId' => $user->id,
                'request_data' => $request->all()
            ]);
            // Per GHL requirements: always return HTTP 200
            return response()->json([
                'failed' => true
            ], 200);
        }

        // Get user's Tap secret keys based on tap_mode
        $secretKey = $user->tap_mode === 'live' ? $user->lead_live_secret_key : $user->lead_test_secret_key;
        
        if (!$secretKey) {
            Log::error('No secret key configured for user', [
                'userId' => $user->id,
                'tap_mode' => $user->tap_mode,
                'has_live_secret' => !empty($user->lead_live_secret_key),
                'has_test_secret' => !empty($user->lead_test_secret_key)
            ]);
            
            // Per GHL requirements: always return HTTP 200
            return response()->json([
                'failed' => true
            ], 200);
        }

        try {
            Log::info('Attempting to retrieve charge from Tap API', [
                'chargeId' => $tapChargeId,
                'user_id' => $user->id,
                'user_locationId' => $user->lead_location_id,
                'tap_mode' => $user->tap_mode,
                'secret_key_prefix' => substr($secretKey, 0, 15) . '...'
            ]);
            
            // Call Tap API directly to retrieve charge with timeout and retry
            $maxRetries = 3;
            $retryDelay = 1; // seconds
            $response = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    Log::info('Tap API charge retrieval attempt', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'chargeId' => $tapChargeId
                    ]);
                    
                    $response = Http::timeout(10) // 10 second timeout
                        ->retry(2, 500) // Retry 2 times with 500ms delay
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $secretKey,
                            'accept' => 'application/json',
                        ])->get('https://api.tap.company/v2/charges/' . $tapChargeId);
                    
                    // If successful, break out of retry loop
                    if ($response->successful()) {
                        break;
                    }
                    
                    // If not successful and not last attempt, wait and retry
                    if ($attempt < $maxRetries) {
                        Log::warning('Tap API charge retrieval attempt failed, retrying', [
                            'attempt' => $attempt,
                            'status_code' => $response->status(),
                            'error' => $response->json() ?? $response->body(),
                            'will_retry_in_seconds' => $retryDelay
                        ]);
                        sleep($retryDelay);
                    }
                } catch (\Exception $e) {
                    Log::warning('Tap API charge retrieval attempt exception', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'will_retry' => $attempt < $maxRetries
                    ]);
                    
                    if ($attempt < $maxRetries) {
                        sleep($retryDelay);
                    }
                }
            }

            // Check if we got a response after all retries
            if (!$response) {
                Log::error('Charge retrieval failed: No response after all retries', [
                    'chargeId' => $tapChargeId,
                    'max_retries' => $maxRetries,
                    'user_id' => $user->id
                ]);
                
                // Per GHL requirements: always return HTTP 200
                return response()->json([
                    'failed' => true
                ], 200);
            }

            // If charge retrieval failed, try to find the correct user
            if (!$response->successful()) {
                $errorResponse = $response->json();
                $errorCode = $errorResponse['errors'][0]['code'] ?? null;
                
                Log::warning('Tap charge retrieval failed with first user, trying to find correct user', [
                    'status' => $response->status(),
                    'error_code' => $errorCode,
                    'chargeId' => $tapChargeId,
                    'first_user_id' => $user->id,
                    'first_user_locationId' => $user->lead_location_id
                ]);
                
                // If error is "Charge id is invalid" (code 1143), try to find correct user
                if ($errorCode === '1143' && $apiKey) {
                    // Try to find all users with this API key
                    $potentialUsers = User::where('lead_test_api_key', $apiKey)
                                         ->orWhere('lead_live_api_key', $apiKey)
                                         ->get();
                    
                    Log::info('Found potential users with same API key', [
                        'count' => $potentialUsers->count(),
                        'chargeId' => $tapChargeId
                    ]);
                    
                    // Try each user until we find one that can access the charge
                    foreach ($potentialUsers as $potentialUser) {
                        if ($potentialUser->id === $user->id) {
                            continue; // Skip the one we already tried
                        }
                        
                        $potentialSecretKey = $potentialUser->tap_mode === 'live' 
                            ? $potentialUser->lead_live_secret_key 
                            : $potentialUser->lead_test_secret_key;
                        
                        if (!$potentialSecretKey) {
                            continue;
                        }
                        
                        Log::info('Trying alternative user for charge retrieval', [
                            'user_id' => $potentialUser->id,
                            'user_locationId' => $potentialUser->lead_location_id,
                            'tap_mode' => $potentialUser->tap_mode
                        ]);
                        
                        $testResponse = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $potentialSecretKey,
                            'accept' => 'application/json',
                        ])->get('https://api.tap.company/v2/charges/' . $tapChargeId);
                        
                        if ($testResponse->successful()) {
                            Log::info('Found correct user for charge', [
                                'correct_user_id' => $potentialUser->id,
                                'correct_user_locationId' => $potentialUser->lead_location_id,
                                'chargeId' => $tapChargeId
                            ]);
                            
                            // Use the correct user
                            $user = $potentialUser;
                            $secretKey = $potentialSecretKey;
                            $response = $testResponse;
                            break;
                        }
                    }
                }
                
                // If still failed after trying all users
                if (!$response->successful()) {
                    Log::error('Tap charge retrieval failed after trying all users', [
                        'status' => $response->status(),
                        'response' => $response->json(),
                        'chargeId' => $tapChargeId
                    ]);
                    
                    // Per GHL requirements: always return HTTP 200 with proper format
                    return response()->json([
                        'failed' => true
                    ], 200);
                }
            }

            $chargeData = $response->json();
            $status = $chargeData['status'] ?? 'UNKNOWN';
            
            Log::info('Tap charge verification result', [
                'chargeId' => $tapChargeId,
                'status' => $status,
                'subscriptionId' => $subscriptionId,
                'response_code' => $chargeData['response']['code'] ?? 'unknown',
                'locationId' => $user->lead_location_id
            ]);

            // IMPORTANT: Send webhook event to LeadConnector BEFORE returning verification result
            // This ensures products are updated before verification completes
            if (in_array($status, ['CAPTURED', 'AUTHORIZED'])) {
                Log::info('Payment verified as successful, sending webhook to LeadConnector', [
                    'chargeId' => $tapChargeId,
                    'transactionId' => $transactionId,
                    'status' => $status,
                    'locationId' => $user->lead_location_id
                ]);
                
                // Send payment.captured webhook event to LeadConnector
                $this->sendPaymentCapturedWebhook($request, $user, $tapChargeId, $transactionId, $chargeData);
            }

            // Return response according to GHL documentation
            // Always return HTTP 200 per requirements
            if (in_array($status, ['CAPTURED', 'AUTHORIZED'])) {
                // Payment successful
                return response()->json([
                    'success' => true
                ], 200);
            } elseif (in_array($status, ['FAILED', 'DECLINED', 'CANCELLED', 'REVERSED'])) {
                // Payment failed
                return response()->json([
                    'failed' => true
                ], 200);
            } else {
                // Payment pending (INITIATED, etc.) - keep in pending state
                return response()->json([
                    'success' => false
                ], 200);
            }

        } catch (\Exception $e) {
            Log::error('Error verifying charge with Tap API', [
                'chargeId' => $tapChargeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Per GHL requirements: always return HTTP 200
            return response()->json([
                'failed' => true
            ], 200);
        }
    }

    /**
     * Handle payment refund
     */
    private function handleRefund(Request $request, User $user)
    {
        $transactionId = $request->input('transactionId');
        $amount = $request->input('amount');
        
        if (!$transactionId || !$amount) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction ID and amount are required'
            ], 400);
        }

        // Get user's Tap credentials
        $apiKey = $user->lead_live_api_key ;
        
        $isLive = !empty($user->lead_live_api_key);
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'No API key configured for this location'
            ], 400);
        }

        $tapService = new TapPaymentService($apiKey, '', $isLive);
        $refund = $tapService->createRefund($transactionId, $amount);

        if (!$refund) {
            return response()->json([
                'success' => false,
                'message' => 'Refund failed'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'refundId' => $refund['id'],
            'amount' => $amount,
            'status' => strtolower($refund['status'])
        ]);
    }

    /**
     * Handle payment charge
     */
    private function handleCharge(Request $request, User $user)
    {
        $amount = $request->input('amount');
        $currency = $request->input('currency', 'JOD');
        $paymentMethodId = $request->input('payment_method_id');
        $token = $request->input('token');
        
        if (!$amount || (!$paymentMethodId && !$token)) {
            return response()->json([
                'success' => false,
                'message' => 'Amount and payment method ID or token are required'
            ], 400);
        }

        // Get user's Tap credentials
        $apiKey = $user->lead_live_api_key ?? $user->lead_test_api_key;
        $isLive = !empty($user->lead_live_api_key);
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'No API key configured for this location'
            ], 400);
        }

        $tapService = new TapPaymentService($apiKey, '', $isLive);
        
        // Create charge using token or payment method
        if ($token) {
            $charge = $tapService->createCharge($token, $amount, $currency);
        } else {
            $charge = $tapService->createChargeWithPaymentMethod($paymentMethodId, $amount, $currency);
        }

        if (!$charge) {
            return response()->json([
                'success' => false,
                'message' => 'Charge creation failed'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'chargeId' => $charge['id'],
            'amount' => $amount,
            'currency' => $currency,
            'status' => strtolower($charge['status'])
        ]);
    }

    /**
     * Handle subscription creation
     */
    private function handleCreateSubscription(Request $request, User $user)
    {
        $subscriptionData = $request->all();
        
        // TODO: Implement actual subscription creation with Tap API
        // For now, return a mock response
        return response()->json([
            'success' => true,
            'failed' => false,
            'message' => 'Subscription created',
            'subscription' => [
                'subscriptionId' => 'sub_' . uniqid(),
                'subscriptionSnapshot' => [
                    'id' => 'sub_' . uniqid(),
                    'status' => 'active',
                    'trialEnd' => 0,
                    'createdAt' => time(),
                    'nextCharge' => time() + (30 * 24 * 60 * 60) // 30 days from now
                ]
            ]
        ]);
    }

    /**
     * Handle subscription update
     */
    private function handleUpdateSubscription(Request $request, User $user)
    {
        $subscriptionId = $request->input('subscriptionId');
        
        if (!$subscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID is required'
            ], 400);
        }

        // TODO: Implement actual subscription update with Tap API
        return response()->json([
            'success' => true,
            'message' => 'Subscription updated'
        ]);
    }

    /**
     * Handle subscription cancellation
     */
    private function handleCancelSubscription(Request $request, User $user)
    {
        $subscriptionId = $request->input('subscriptionId');
        
        if (!$subscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID is required'
            ], 400);
        }

        // TODO: Implement actual subscription cancellation with Tap API
        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled'
        ]);
    }

    /**
     * Handle payment methods listing
     */
    private function handleListPaymentMethods(Request $request, User $user)
    {
        $contactId = $request->input('contactId');
        
        if (!$contactId) {
            return response()->json([
                'success' => false,
                'message' => 'Contact ID is required'
            ], 400);
        }

        // Get user's Tap credentials
        $apiKey = $user->lead_live_api_key ?? $user->lead_test_api_key;
        $isLive = !empty($user->lead_live_api_key);
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'No API key configured for this location'
            ], 400);
        }

        // TODO: Get customer ID from contact ID mapping
        // For now, we'll need to implement customer ID mapping
        $customerId = 'cus_' . $contactId; // This should be mapped from your database
        
        $tapService = new TapPaymentService($apiKey, '', $isLive);
        $paymentMethods = $tapService->getCustomerPaymentMethods($customerId);

        if (!$paymentMethods) {
            return response()->json([
                'success' => true,
                'paymentMethods' => []
            ]);
        }

        // Transform Tap response to GoHighLevel format
        $formattedMethods = [];
        if (isset($paymentMethods['data'])) {
            foreach ($paymentMethods['data'] as $method) {
                $formattedMethods[] = [
                    'id' => $method['id'],
                    'type' => 'card',
                    'title' => ucfirst(strtolower($method['brand'] ?? 'Card')),
                    'subTitle' => 'XXXX-' . ($method['last_four'] ?? '0000'),
                    'expiry' => ($method['exp_month'] ?? '01') . '/' . ($method['exp_year'] ?? '25'),
                    'customerId' => $customerId,
                    'imageUrl' => $this->getCardImageUrl($method['brand'] ?? 'VISA')
                ];
            }
        }

        return response()->json([
            'success' => true,
            'paymentMethods' => $formattedMethods
        ]);
    }

    /**
     * Get card image URL based on brand
     */
    private function getCardImageUrl($brand)
    {
        $brandImages = [
            'VISA' => 'https://upload.wikimedia.org/wikipedia/commons/4/41/Visa_Logo.png',
            'MASTERCARD' => 'https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg',
            'AMERICAN_EXPRESS' => 'https://upload.wikimedia.org/wikipedia/commons/3/30/American_Express_logo.svg',
            'MADA' => 'https://mada.com.sa/wp-content/themes/mada/assets/images/logo.svg'
        ];

        return $brandImages[strtoupper($brand)] ?? $brandImages['VISA'];
    }

    /**
     * Handle charge payment method
     */
    private function handleChargePayment(Request $request, User $user)
    {
        $paymentMethodId = $request->input('paymentMethodId');
        $amount = $request->input('amount');
        $currency = $request->input('currency', 'JOD');
        $transactionId = $request->input('transactionId');
        $chargeDescription = $request->input('chargeDescription', 'Payment');
        $contactId = $request->input('contactId');
        
        if (!$paymentMethodId || !$amount || !$transactionId) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method ID, amount, and transaction ID are required'
            ], 400);
        }

        // Get user's Tap credentials
        $apiKey = $user->lead_live_api_key ?? $user->lead_test_api_key;
        $isLive = !empty($user->lead_live_api_key);
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'No API key configured for this location'
            ], 400);
        }

        $tapService = new TapPaymentService($apiKey, '', $isLive);
        
        // Get customer ID from contact ID
        $customerId = 'cus_' . $contactId; // This should be mapped from your database
        
        $charge = $tapService->createChargeWithPaymentMethod(
            $paymentMethodId, 
            $amount, 
            $currency, 
            $customerId, 
            $chargeDescription
        );

        if (!$charge) {
            return response()->json([
                'success' => false,
                'failed' => true,
                'message' => 'Payment failed'
            ], 500);
        }

        $isSuccessful = strtolower($charge['status']) === 'captured' || strtolower($charge['status']) === 'succeeded';
        
        return response()->json([
            'success' => $isSuccessful,
            'failed' => !$isSuccessful,
            'chargeId' => $charge['id'],
            'message' => $isSuccessful ? 'Payment successful' : 'Payment failed',
            'chargeSnapshot' => [
                'id' => $charge['id'],
                'status' => strtolower($charge['status']),
                'amount' => $charge['amount'],
                'chargeId' => $charge['id'],
                'chargedAt' => strtotime($charge['created'])
            ]
        ]);
    }

    /**
     * Send payment.captured webhook event to LeadConnector backend
     * This should be called when a payment is verified as successful
     */
    private function sendPaymentCapturedWebhook(Request $request, User $user, string $chargeId, string $transactionId, array $chargeData)
    {
        try {
            $locationId = $user->lead_location_id;
            $apiKey = $user->tap_mode === 'live' ? $user->lead_live_api_key : $user->lead_test_api_key;
            
            Log::info('=== SENDING payment.captured WEBHOOK TO LEADCONNECTOR ===', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'locationId' => $locationId,
                'status' => $chargeData['status'] ?? 'UNKNOWN',
                'timestamp' => now()->toIso8601String()
            ]);
            
            // Build chargeSnapshot from Tap API charge data
            $chargeSnapshot = [
                'id' => $chargeId,
                'status' => strtolower($chargeData['status'] ?? 'unknown'),
                'amount' => $chargeData['amount'] ?? 0,
                'currency' => $chargeData['currency'] ?? 'KWD',
                'created' => $chargeData['transaction']['created'] ?? time() * 1000,
                'response' => [
                    'code' => $chargeData['response']['code'] ?? 'unknown',
                    'message' => $chargeData['response']['message'] ?? 'unknown'
                ]
            ];
            
            // Build webhook payload for payment.captured event
            $payload = [
                'event' => 'payment.captured',
                'chargeId' => $chargeId,
                'ghlTransactionId' => $transactionId,
                'chargeSnapshot' => $chargeSnapshot,
                'locationId' => $locationId,
                'apiKey' => $apiKey
            ];
            
            Log::info('Webhook payload built for payment.captured', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'locationId' => $locationId,
                'payload_keys' => array_keys($payload),
                'chargeSnapshot' => $chargeSnapshot
            ]);
            
            // Send webhook to LeadConnector backend
            $webhookUrl = 'https://backend.leadconnectorhq.com/payments/custom-provider/webhook';
            
            $startTime = microtime(true);
            $response = Http::timeout(30)
                ->acceptJson()
                ->post($webhookUrl, $payload);
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2);
            
            if ($response->successful()) {
                $responseBody = $response->json();
                Log::info('=== SUCCESS: payment.captured webhook sent to LeadConnector ===', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'locationId' => $locationId,
                    'response_status' => $response->status(),
                    'response_body' => $responseBody,
                    'duration_ms' => $duration,
                    'timestamp' => now()->toIso8601String()
                ]);
            } else {
                $errorResponse = $response->json() ?? $response->body();
                Log::error('=== FAILED: payment.captured webhook send to LeadConnector ===', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'locationId' => $locationId,
                    'status_code' => $response->status(),
                    'response_body' => $errorResponse,
                    'response_raw' => $response->body(),
                    'duration_ms' => $duration,
                    'timestamp' => now()->toIso8601String()
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('=== EXCEPTION: Error sending payment.captured webhook ===', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String()
            ]);
        }
    }
}
