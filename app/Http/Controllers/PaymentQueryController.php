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
            // This is CRITICAL - we need to find the user who created the charge, not just any user with the API key
            if (!$user && $request->input('chargeId') && $apiKey) {
                $chargeId = $request->input('chargeId');
                Log::info('Attempting to extract locationId from charge metadata', [
                    'chargeId' => $chargeId,
                    'reason' => 'No locationId provided in request, need to get from charge'
                ]);
                
                // First, try to find all users with this API key
                $potentialUsers = User::where('lead_test_api_key', $apiKey)
                                     ->orWhere('lead_live_api_key', $apiKey)
                                     ->get();
                
                Log::info('Found potential users with API key, checking which one owns the charge', [
                    'chargeId' => $chargeId,
                    'potential_users_count' => $potentialUsers->count()
                ]);
                
                // Try each user (both test and live modes) to find which one can access the charge
                foreach ($potentialUsers as $potentialUser) {
                    foreach (['test', 'live'] as $mode) {
                        $modeSecretKey = $mode === 'live' 
                            ? $potentialUser->lead_live_secret_key 
                            : $potentialUser->lead_test_secret_key;
                        
                        if (!$modeSecretKey) {
                            continue;
                        }
                        
                        try {
                            Log::info('Checking if user owns charge', [
                                'user_id' => $potentialUser->id,
                                'user_locationId' => $potentialUser->lead_location_id,
                                'mode' => $mode,
                    'chargeId' => $chargeId
                ]);
                
                            $testResponse = Http::timeout(10)
                                ->withHeaders([
                                    'Authorization' => 'Bearer ' . $modeSecretKey,
                                    'accept' => 'application/json',
                                ])->get('https://api.tap.company/v2/charges/' . $chargeId);
                            
                            if ($testResponse->successful()) {
                                $chargeData = $testResponse->json();
                                
                                // Extract locationId from charge metadata
                                $chargeLocationId = null;
                                if (isset($chargeData['metadata']['udf3'])) {
                                    $udf3 = $chargeData['metadata']['udf3'];
                                    if (preg_match('/Location:\s*(.+)/', $udf3, $matches)) {
                                        $chargeLocationId = trim($matches[1]);
                                    }
                                }
                                
                                Log::info('✅ Found charge and extracted locationId', [
                                    'charge_accessible_by_user_id' => $potentialUser->id,
                                    'charge_accessible_by_locationId' => $potentialUser->lead_location_id,
                                    'charge_locationId_from_metadata' => $chargeLocationId,
                                    'mode' => $mode,
                                    'chargeId' => $chargeId,
                                    'locationId_matches' => $chargeLocationId === $potentialUser->lead_location_id
                                ]);
                                
                                // CRITICAL: Find the user by the locationId from charge metadata
                                // This ensures we use the user who created the charge, not just any user who can access it
                                if ($chargeLocationId) {
                                    $correctUser = User::where('lead_location_id', $chargeLocationId)->first();
                                    
                                    if ($correctUser) {
                                        Log::info('✅ Found correct user by locationId from charge metadata', [
                                            'correct_user_id' => $correctUser->id,
                                            'correct_user_locationId' => $correctUser->lead_location_id,
                                            'charge_locationId' => $chargeLocationId,
                                            'chargeId' => $chargeId
                                        ]);
                                        
                                        // Use the correct user
                                        $user = $correctUser;
                                        
                                        // Determine the correct mode for this user
                                        // Try to verify which mode this user used to create the charge
                                        $testKey = $correctUser->lead_test_secret_key;
                                        $liveKey = $correctUser->lead_live_secret_key;
                                        
                                        // Check which mode can access the charge
                                        $finalMode = $mode; // Default to the mode we already verified works
                                        
                                        // Double-check: verify the correct user can access with their mode
                                        $userSecretKey = $finalMode === 'live' ? $liveKey : $testKey;
                                        if ($userSecretKey) {
                                            try {
                                                $verifyResponse = Http::timeout(10)
                                                    ->withHeaders([
                                                        'Authorization' => 'Bearer ' . $userSecretKey,
                                                        'accept' => 'application/json',
                                                    ])->get('https://api.tap.company/v2/charges/' . $chargeId);
                                                
                                                if (!$verifyResponse->successful()) {
                                                    // Try opposite mode
                                                    $oppositeMode = $finalMode === 'live' ? 'test' : 'live';
                                                    $oppositeSecretKey = $oppositeMode === 'live' ? $liveKey : $testKey;
                                                    
                                                    if ($oppositeSecretKey) {
                                                        $verifyResponse2 = Http::timeout(10)
                                                            ->withHeaders([
                                                                'Authorization' => 'Bearer ' . $oppositeSecretKey,
                                                                'accept' => 'application/json',
                                                            ])->get('https://api.tap.company/v2/charges/' . $chargeId);
                                                        
                                                        if ($verifyResponse2->successful()) {
                                                            $finalMode = $oppositeMode;
                                                            Log::info('Switched to opposite mode for correct user', [
                                                                'user_id' => $correctUser->id,
                                                                'final_mode' => $finalMode
                                                            ]);
                                                        }
                                                    }
                                                }
                                            } catch (\Exception $e) {
                                                Log::warning('Error verifying mode for correct user', [
                                                    'user_id' => $correctUser->id,
                                                    'error' => $e->getMessage()
                                                ]);
                                            }
                                        }
                                        
                                        $user->tap_mode = $finalMode;
                                        $locationId = $chargeLocationId;
                                        
                                        Log::info('Using correct user for verification', [
                                            'user_id' => $user->id,
                                            'user_locationId' => $user->lead_location_id,
                                            'tap_mode' => $finalMode,
                                            'chargeId' => $chargeId
                                        ]);
                                        
                                        break 2; // Break out of both loops
                                    } else {
                                        Log::warning('Could not find user with locationId from charge metadata', [
                                            'charge_locationId' => $chargeLocationId,
                                            'chargeId' => $chargeId
                                        ]);
                                        
                                        // Fallback: use the user who can access the charge
                                        $user = $potentialUser;
                                        $user->tap_mode = $mode;
                                        $locationId = $chargeLocationId;
                                        break 2;
                                    }
                                } else {
                                    // No locationId in metadata - use the user who can access the charge
                                    Log::warning('No locationId found in charge metadata, using user who can access charge', [
                                        'user_id' => $potentialUser->id,
                                        'chargeId' => $chargeId
                                    ]);
                                    
                                    $user = $potentialUser;
                                    $user->tap_mode = $mode;
                                    break 2;
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning('Error checking charge ownership', [
                                'user_id' => $potentialUser->id,
                                'mode' => $mode,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                    
                    if ($user) {
                        break;
                    }
                }
            }
            
            // Strategy 3: Find user by API key (only if we still don't have a user)
            // This is a fallback - should rarely be needed if Strategy 2 works
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
            
            // Call Tap API directly to retrieve charge with timeout
            // Note: We don't retry here if we get error 1143 (invalid charge), as it means wrong user/mode
            $response = null;
            $errorCode = null;
            
            try {
                Log::info('Tap API charge retrieval attempt', [
                    'chargeId' => $tapChargeId,
                    'user_id' => $user->id,
                    'tap_mode' => $user->tap_mode
                ]);
                
                $response = Http::timeout(10) // 10 second timeout
                    ->withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'accept' => 'application/json',
            ])->get('https://api.tap.company/v2/charges/' . $tapChargeId);

                // Extract error code if request failed
            if (!$response->successful()) {
                $errorResponse = $response->json();
                $errorCode = $errorResponse['errors'][0]['code'] ?? null;
                
                    Log::warning('Tap API charge retrieval failed', [
                        'status_code' => $response->status(),
                        'error_code' => $errorCode,
                        'error_description' => $errorResponse['errors'][0]['description'] ?? null,
                        'chargeId' => $tapChargeId,
                        'user_id' => $user->id,
                        'tap_mode' => $user->tap_mode
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Tap API charge retrieval exception', [
                    'error' => $e->getMessage(),
                    'chargeId' => $tapChargeId,
                    'user_id' => $user->id
                ]);
                
                // Try to extract error from exception message if it contains JSON
                if (preg_match('/"code":"(\d+)"/', $e->getMessage(), $matches)) {
                    $errorCode = $matches[1];
                }
            }

            // Check if we got a response
            if (!$response) {
                Log::error('Charge retrieval failed: No response received', [
                    'chargeId' => $tapChargeId,
                    'user_id' => $user->id
                ]);
                
                // Per GHL requirements: always return HTTP 200
                return response()->json([
                    'failed' => true
                ], 200);
            }

            // If charge retrieval failed, try to find the correct user or mode
            if (!$response->successful()) {
                // If error code wasn't extracted yet, get it from response
                if (!$errorCode) {
                    $errorResponse = $response->json();
                    $errorCode = $errorResponse['errors'][0]['code'] ?? null;
                }
                
                Log::warning('Tap charge retrieval failed, attempting to find correct user/mode', [
                    'status' => $response->status(),
                    'error_code' => $errorCode,
                    'chargeId' => $tapChargeId,
                    'first_user_id' => $user->id,
                    'first_user_locationId' => $user->lead_location_id,
                    'first_user_tap_mode' => $user->tap_mode
                ]);
                
                // If error is "Charge id is invalid" (code 1143), try alternative approaches
                if ($errorCode === '1143') {
                    $foundCorrectUser = false;
                    
                    // Strategy 1: Try opposite mode (test vs live) for same user
                    $oppositeMode = $user->tap_mode === 'live' ? 'test' : 'live';
                    $oppositeSecretKey = $oppositeMode === 'live' 
                        ? $user->lead_live_secret_key 
                        : $user->lead_test_secret_key;
                    
                    if ($oppositeSecretKey) {
                        Log::info('Trying opposite mode for same user', [
                            'user_id' => $user->id,
                            'current_mode' => $user->tap_mode,
                            'trying_mode' => $oppositeMode,
                            'chargeId' => $tapChargeId
                        ]);
                        
                        try {
                            $testResponse = Http::timeout(10)
                                ->withHeaders([
                                    'Authorization' => 'Bearer ' . $oppositeSecretKey,
                                    'accept' => 'application/json',
                                ])->get('https://api.tap.company/v2/charges/' . $tapChargeId);
                            
                            if ($testResponse->successful()) {
                                Log::info('✅ Found correct mode for user', [
                                    'user_id' => $user->id,
                                    'correct_mode' => $oppositeMode,
                                    'chargeId' => $tapChargeId
                                ]);
                                
                                $user->tap_mode = $oppositeMode; // Update mode for this request
                                $secretKey = $oppositeSecretKey;
                                $response = $testResponse;
                                $foundCorrectUser = true;
                            }
                        } catch (\Exception $e) {
                            Log::warning('Failed to retrieve charge with opposite mode', [
                                'error' => $e->getMessage(),
                                'mode' => $oppositeMode
                            ]);
                        }
                    }
                    
                    // Strategy 2: Try to find all users with this API key (if not found yet)
                    if (!$foundCorrectUser && $apiKey) {
                    // Try to find all users with this API key
                    $potentialUsers = User::where('lead_test_api_key', $apiKey)
                                         ->orWhere('lead_live_api_key', $apiKey)
                                         ->get();
                    
                    Log::info('Found potential users with same API key', [
                        'count' => $potentialUsers->count(),
                        'chargeId' => $tapChargeId
                    ]);
                    
                        // Try each user (both modes) until we find one that can access the charge
                    foreach ($potentialUsers as $potentialUser) {
                            // Try both test and live modes for each user
                            foreach (['test', 'live'] as $mode) {
                                $modeSecretKey = $mode === 'live' 
                            ? $potentialUser->lead_live_secret_key 
                            : $potentialUser->lead_test_secret_key;
                        
                                if (!$modeSecretKey) {
                            continue;
                        }
                        
                                // Skip if we already tried this user+mode combination
                                if ($potentialUser->id === $user->id && 
                                    (($mode === 'live' && $user->tap_mode === 'live') || 
                                     ($mode === 'test' && $user->tap_mode === 'test'))) {
                                    continue;
                                }
                                
                                Log::info('Trying alternative user/mode for charge retrieval', [
                            'user_id' => $potentialUser->id,
                            'user_locationId' => $potentialUser->lead_location_id,
                                    'mode' => $mode,
                                    'chargeId' => $tapChargeId
                                ]);
                                
                                try {
                                    $testResponse = Http::timeout(10)
                                        ->withHeaders([
                                            'Authorization' => 'Bearer ' . $modeSecretKey,
                            'accept' => 'application/json',
                        ])->get('https://api.tap.company/v2/charges/' . $tapChargeId);
                        
                        if ($testResponse->successful()) {
                                        Log::info('✅ Found correct user/mode for charge', [
                                'correct_user_id' => $potentialUser->id,
                                'correct_user_locationId' => $potentialUser->lead_location_id,
                                            'correct_mode' => $mode,
                                'chargeId' => $tapChargeId
                            ]);
                            
                                        // Use the correct user and mode
                            $user = $potentialUser;
                                        $user->tap_mode = $mode; // Update mode for this request
                                        $secretKey = $modeSecretKey;
                            $response = $testResponse;
                                        $foundCorrectUser = true;
                                        break 2; // Break out of both loops
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('Failed to retrieve charge with alternative user/mode', [
                                        'user_id' => $potentialUser->id,
                                        'mode' => $mode,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                            
                            if ($foundCorrectUser) {
                            break;
                            }
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
                
                // Check if we've already sent a webhook for this charge recently (prevent duplicates)
                $webhookCacheKey = 'webhook_sent_' . $tapChargeId . '_' . $transactionId;
                $recentlySent = cache()->get($webhookCacheKey);
                
                if ($recentlySent) {
                    Log::warning('Webhook already sent recently for this charge, skipping duplicate', [
                        'chargeId' => $tapChargeId,
                        'transactionId' => $transactionId,
                        'last_sent_at' => $recentlySent
                    ]);
                } else {
                // Send payment.captured webhook event to LeadConnector
                    // Note: The webhook function will cache success internally
                $this->sendPaymentCapturedWebhook($request, $user, $tapChargeId, $transactionId, $chargeData);
                }
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
            $isLive = $user->tap_mode === 'live';
            
            // Validate API key exists for the current mode
            if (empty($apiKey)) {
                Log::error('=== WEBHOOK ERROR: API key missing for current mode ===', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'locationId' => $locationId,
                    'tap_mode' => $user->tap_mode,
                    'is_live' => $isLive,
                    'has_live_key' => !empty($user->lead_live_api_key),
                    'has_test_key' => !empty($user->lead_test_api_key)
                ]);
                return; // Cannot send webhook without API key
            }
            
            Log::info('=== SENDING payment.captured WEBHOOK TO LEADCONNECTOR ===', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'locationId' => $locationId,
                'status' => $chargeData['status'] ?? 'UNKNOWN',
                'tap_mode' => $user->tap_mode,
                'is_live' => $isLive,
                'api_key_length' => strlen($apiKey),
                'api_key_prefix' => substr($apiKey, 0, 10) . '...',
                'timestamp' => now()->toIso8601String()
            ]);
            
            // Build chargeSnapshot from Tap API charge data
            // GHL expects: id, status, amount, currency, chargedAt (timestamp), response (code, message)
            $transactionCreated = $chargeData['transaction']['created'] ?? $chargeData['created'] ?? time() * 1000;
            $chargedAt = is_numeric($transactionCreated) ? (int)$transactionCreated : strtotime($transactionCreated) * 1000;
            
            $chargeSnapshot = [
                'id' => $chargeId,
                'status' => strtolower($chargeData['status'] ?? 'unknown'),
                'amount' => $chargeData['amount'] ?? 0,
                'currency' => $chargeData['currency'] ?? 'KWD',
                'chargedAt' => $chargedAt, // GHL expects chargedAt (timestamp in milliseconds)
                'response' => [
                    'code' => $chargeData['response']['code'] ?? 'unknown',
                    'message' => $chargeData['response']['message'] ?? 'unknown'
                ]
            ];
            
            // Validate required fields before building payload
            $validationErrors = [];
            if (empty($chargeId)) {
                $validationErrors[] = 'chargeId is missing';
            }
            if (empty($transactionId)) {
                $validationErrors[] = 'ghlTransactionId (transactionId) is missing';
            }
            if (empty($locationId)) {
                $validationErrors[] = 'locationId is missing';
            }
            if (empty($apiKey)) {
                $validationErrors[] = 'apiKey is missing';
            }
            if (empty($chargeSnapshot['id'])) {
                $validationErrors[] = 'chargeSnapshot.id is missing';
            }
            
            if (!empty($validationErrors)) {
                Log::error('=== WEBHOOK VALIDATION FAILED ===', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'locationId' => $locationId,
                    'validation_errors' => $validationErrors,
                    'chargeSnapshot' => $chargeSnapshot
                ]);
                return; // Don't send invalid webhook
            }
            
            // Build webhook payload for payment.captured event
            $payload = [
                'event' => 'payment.captured',
                'chargeId' => $chargeId,
                'ghlTransactionId' => $transactionId,
                'chargeSnapshot' => $chargeSnapshot,
                'locationId' => $locationId,
                'apiKey' => $apiKey
            ];
            
            // Send webhook to LeadConnector backend with retry logic
            $webhookUrl = 'https://backend.leadconnectorhq.com/payments/custom-provider/webhook';
            
            Log::info('Webhook payload built for payment.captured', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'locationId' => $locationId,
                'tap_mode' => $user->tap_mode,
                'is_live' => $isLive,
                'apiKey_length' => strlen($apiKey),
                'apiKey_prefix' => substr($apiKey, 0, 10) . '...',
                'payload_keys' => array_keys($payload),
                'chargeSnapshot' => $chargeSnapshot,
                'webhook_url' => $webhookUrl,
                'payload_json' => json_encode($payload) // Log full payload for debugging
            ]);
            $maxRetries = 3;
            $retryDelay = 2; // seconds
            $response = null;
            $webhookSuccess = false;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    Log::info('Webhook send attempt', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'chargeId' => $chargeId,
                        'transactionId' => $transactionId,
                        'tap_mode' => $user->tap_mode,
                        'is_live' => $isLive,
                        'webhook_url' => $webhookUrl
                    ]);
            
            $startTime = microtime(true);
            $response = Http::timeout(30)
                        ->retry(1, 1000) // Retry once with 1 second delay for network issues
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
                            'tap_mode' => $user->tap_mode,
                            'is_live' => $isLive,
                    'response_status' => $response->status(),
                    'response_body' => $responseBody,
                    'duration_ms' => $duration,
                            'attempt' => $attempt,
                    'timestamp' => now()->toIso8601String()
                ]);
                        $webhookSuccess = true;
                        
                        // Cache that we successfully sent this webhook (expires in 5 minutes to prevent duplicates)
                        $webhookCacheKey = 'webhook_sent_' . $chargeId . '_' . $transactionId;
                        cache()->put($webhookCacheKey, now()->toIso8601String(), 300);
                        
                        break; // Success, exit retry loop
            } else {
                $errorResponse = $response->json() ?? $response->body();
                        Log::warning('Webhook send attempt failed', [
                            'attempt' => $attempt,
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'locationId' => $locationId,
                    'status_code' => $response->status(),
                    'response_body' => $errorResponse,
                    'response_raw' => $response->body(),
                    'duration_ms' => $duration,
                            'will_retry' => $attempt < $maxRetries
                        ]);
                        
                        // If it's a client error (4xx), don't retry - it's likely a payload issue
                        if ($response->status() >= 400 && $response->status() < 500) {
                            Log::error('Webhook rejected by server (client error), not retrying', [
                                'status_code' => $response->status(),
                                'response' => $errorResponse
                            ]);
                            break;
                        }
                        
                        // Retry for server errors (5xx) or network issues
                        if ($attempt < $maxRetries) {
                            sleep($retryDelay);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Webhook send attempt exception', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'will_retry' => $attempt < $maxRetries
                    ]);
                    
                    if ($attempt < $maxRetries) {
                        sleep($retryDelay);
                    }
                }
            }
            
            // Log final result
            if (!$webhookSuccess) {
                $errorResponse = $response ? ($response->json() ?? $response->body()) : 'No response received';
                Log::error('=== FAILED: payment.captured webhook send to LeadConnector after all retries ===', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'locationId' => $locationId,
                    'tap_mode' => $user->tap_mode,
                    'is_live' => $isLive,
                    'max_retries' => $maxRetries,
                    'final_status_code' => $response ? $response->status() : 'N/A',
                    'final_response_body' => $errorResponse,
                    'final_response_raw' => $response ? $response->body() : 'N/A',
                    'webhook_url' => $webhookUrl,
                    'timestamp' => now()->toIso8601String(),
                    'payload_sent' => $payload // Log payload for debugging
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
