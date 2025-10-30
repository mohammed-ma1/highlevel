<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;
class ClientIntegrationController extends Controller
{
    public function connect(Request $request)
    {
        // Log all requests to this endpoint for debugging
        Log::info('Connect endpoint called', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'all_params' => $request->all(),
            'query_params' => $request->query(),
            'has_code' => $request->has('code'),
            'code_value' => $request->input('code')
        ]);
        
        // Check if this is a proper OAuth request
        if (!$request->has('code')) {
            Log::warning('Connect endpoint called without authorization code', [
                'url' => $request->fullUrl(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'message' => 'This endpoint requires OAuth authorization code. Use /provider/connect-or-disconnect for form submissions.',
                'error' => 'Missing authorization code',
                'received_params' => $request->all()
            ], 400);
        }
        
        $tokenUrl = config('services.external_auth.token_url', 'https://services.leadconnectorhq.com/oauth/token');

        try {
            // 1) Exchange auth code -> token
            $tokenPayload = [
                'grant_type'    => 'authorization_code',
                'client_id'     => config('services.external_auth.client_id'),
                'client_secret' => config('services.external_auth.client_secret'),
                'code'          => $request->input('code'),
                // 'redirect_uri'  => config('services.external_auth.redirect_uri', 'https://dashboard.mediasolution.io/connect'), // Include redirect_uri
            ];


            
            Log::info('OAuth token request', [
                'url' => $tokenUrl,
                'payload' => $tokenPayload,
                'client_id' => config('services.external_auth.client_id'),
                'has_client_secret' => !empty(config('services.external_auth.client_secret'))
            ]);
            
            $tokenResponse = Http::timeout(15)
                ->acceptJson()
                ->asForm()
                ->post($tokenUrl, $tokenPayload);
                
            // Log the raw response for debugging
            Log::info('OAuth response received', [
                'status' => $tokenResponse->status(),
                'successful' => $tokenResponse->successful(),
                'failed' => $tokenResponse->failed(),
                'body' => $tokenResponse->body(),
                'json' => $tokenResponse->json()
            ]);



            if ($tokenResponse->failed()) {
                Log::error('OAuth token exchange failed', [
                    'status' => $tokenResponse->status(),
                    'response_body' => $tokenResponse->body(),
                    'response_json' => $tokenResponse->json(),
                    'headers' => $tokenResponse->headers()
                ]);
                
                return response()->json([
                    'message' => 'OAuth exchange failed',
                    'status'  => $tokenResponse->status(),
                    'error'   => $tokenResponse->json() ?: $tokenResponse->body(),
                ], 502);
            }

            $body = $tokenResponse->json();


            
            Log::info('OAuth response parsed successfully', [
                'has_access_token' => !empty($body['access_token']),
                'has_refresh_token' => !empty($body['refresh_token']),
                'locationId' => $body['locationId'] ?? 'missing',
                'userType' => $body['userType'] ?? 'missing'
            ]);

            // Extract what we need
            $accessToken   = $body['access_token'] ?? null;
            $refreshToken  = $body['refresh_token'] ?? null;
            $tokenType     = $body['token_type'] ?? 'Bearer';
            $expiresIn     = (int) ($body['expires_in'] ?? 0);
            $scope         = $body['scope'] ?? null;
            
            Log::info('Extracted OAuth data', [
                'has_access_token' => !empty($accessToken),
                'has_refresh_token' => !empty($refreshToken),
                'token_type' => $tokenType,
                'expires_in' => $expiresIn,
                'has_scope' => !empty($scope)
            ]);

            $refreshTokenId = $body['refreshTokenId'] ?? null;
            $userType       = $body['userType'] ?? null;    // "Location"
            $companyId      = $body['companyId'] ?? null;
            $locationId     = $body['locationId'] ?? null;
            $isBulk         = (bool) ($body['isBulkInstallation'] ?? false);
            $providerUserId = $body['userId'] ?? null;

            Log::info('Validating OAuth data', [
                'has_access_token' => !empty($accessToken),
                'has_location_id' => !empty($locationId),
                'access_token_length' => $accessToken ? strlen($accessToken) : 0,
                'location_id_value' => $locationId
            ]);
            
            if (!$accessToken || !$locationId) {
                Log::error('OAuth validation failed', [
                    'missing_access_token' => empty($accessToken),
                    'missing_location_id' => empty($locationId)
                ]);
                return response()->json([
                    'message' => 'Invalid OAuth response (missing access_token or locationId)',
                    'json'    => $body,
                ], 502);
            }

            // 2) Find or create a local user tied to this location
            // Prefer to find by lead_location_id (unique per location)
            $user = User::where('lead_location_id', $locationId)->first();
            
            // Fallback: if no user found by location_id, try to find by email pattern
            if (!$user) {
                $baseEmail = "location_{$locationId}@leadconnector.local";
                $user = User::where('email', 'like', "location_{$locationId}%@leadconnector.local")->first();
                
                if ($user) {
                    Log::info('Found existing user by email pattern', [
                        'locationId' => $locationId,
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);
                }
            }

            if (!$user) {
                // No user? Create a minimal one.
                // Generate a unique email to avoid duplicate key errors
                $baseEmail = "location_{$locationId}@leadconnector.local";
                $placeholderEmail = $baseEmail;
                $counter = 1;
                
                // Check if email already exists and generate a unique one
                while (User::where('email', $placeholderEmail)->exists()) {
                    $placeholderEmail = "location_{$locationId}_{$counter}@leadconnector.local";
                    $counter++;
                }

                Log::info('Creating new user', [
                    'locationId' => $locationId,
                    'generated_email' => $placeholderEmail,
                    'email_attempts' => $counter
                ]);

                $user = new User();
                $user->name = "Location {$locationId}";
                $user->email = $placeholderEmail;
                $user->password = Hash::make(Str::random(40));
            }

            // 3) Fill OAuth fields (ensure you added these columns + casts as discussed)
            // Set fields one by one to identify which one causes the issue
            try {
                $user->lead_access_token = $accessToken;
                Log::info('Set lead_access_token successfully', ['token_length' => strlen($accessToken)]);
            } catch (\Exception $e) {
                Log::error('Failed to set lead_access_token', ['error' => $e->getMessage()]);
            }

            try {
                $user->lead_refresh_token = $refreshToken;
                Log::info('Set lead_refresh_token successfully', ['token_length' => strlen($refreshToken)]);
            } catch (\Exception $e) {
                Log::error('Failed to set lead_refresh_token', ['error' => $e->getMessage()]);
            }

            $user->lead_token_type            = $tokenType;
            $user->lead_expires_in            = $expiresIn ?: null;
            $user->lead_token_expires_at      = $expiresIn ? now()->addSeconds($expiresIn) : null;

            $user->lead_scope                 = $scope;
            $user->lead_refresh_token_id      = $refreshTokenId;
            $user->lead_user_type             = $userType;
            $user->lead_company_id            = $companyId;
            $user->lead_location_id           = $locationId;
            $user->lead_user_id               = $providerUserId;
            $user->lead_is_bulk_installation  = $isBulk;

            // Log the data before saving for debugging
            Log::info('About to save user with data', [
                'user_id' => $user->id,
                'is_new_user' => !$user->exists,
                'user_attributes' => $user->getAttributes(),
                'locationId' => $locationId,
                'access_token_length' => $accessToken ? strlen($accessToken) : 0,
                'refresh_token_length' => $refreshToken ? strlen($refreshToken) : 0
            ]);

            try {
                $user->save();
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle database constraint violations specifically
                Log::error('Database constraint violation when saving user', [
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'sql_state' => $e->errorInfo[0] ?? 'unknown',
                    'driver_code' => $e->errorInfo[1] ?? 'unknown',
                    'error_message' => $e->errorInfo[2] ?? 'unknown',
                    'user_data' => $user->toArray(),
                    'user_attributes' => $user->getAttributes(),
                    'locationId' => $locationId
                ]);
                
                return response()->json([
                    'message' => 'Database constraint violation - user may already exist',
                    'error' => $e->getMessage(),
                    'error_type' => 'database_constraint'
                ], 409); // 409 Conflict
            } catch (\Exception $e) {
                Log::error('Failed to save user', [
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                    'error_trace' => $e->getTraceAsString(),
                    'user_data' => $user->toArray(),
                    'user_attributes' => $user->getAttributes(),
                    'user_dirty' => $user->getDirty(),
                    'locationId' => $locationId,
                    'validation_errors' => method_exists($user, 'getErrors') ? $user->getErrors() : 'No validation errors method'
                ]);
                
                return response()->json([
                    'message' => 'Failed to save user data',
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e)
                ], 500);
            }

           // dd($user);

            Log::info('User saved successfully', [
                'user_id' => $user->id,
                'locationId' => $locationId
            ]);

            // 4) (Optional) Associate app ↔ location (provider)
            $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
                        . '?locationId=' . urlencode($locationId);
                
              $providerPayload = [
            'name'        => 'Tap Integration',
            'description' => 'Supports Visa and MasterCard payments via Tap Card SDK, with secure token generation for each transaction. KNET payments redirect customers to the KNET checkout page. The resulting token or KNET source ID is compatible with the Charge API. Note: PayPal is not supported.',
            'paymentsUrl' => 'https://dashboard.mediasolution.io/charge',
            'queryUrl'    => 'https://dashboard.mediasolution.io/api/payment/query',
            'imageUrl'    => 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg',
            ];

            

            $providerResp = Http::timeout(40)
                ->acceptJson()
                ->withToken($accessToken)
                ->withHeaders(['Version' => '2021-07-28'])
                ->post($providerUrl, $providerPayload); 

            if ($providerResp->failed()) {
                Log::warning('Provider association failed', [
                    'status' => $providerResp->status(),
                    'body'   => $providerResp->json(),
                ]);
                // Not fatal to user creation, but you can choose to 502 here if you want
            }


            return response()->json([
                'message' => 'Connected & user saved',
                'user_id' => $user->id,
                'locationId' => $locationId,
                'provider' => $providerResp->json(),
            ], 200);

        } catch (\Throwable $e) {
            Log::error('Integration failed', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Unexpected error'], 500);
        }
    }

    public function connectOrDisconnect(Request $request)
    {
        // Log the incoming request for debugging
        Log::info('ConnectOrDisconnect method called', [
            'action' => $request->input('action'),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'all_params' => $request->all()
        ]);
        
        // 0) Which action?
        $action = strtolower((string) $request->input('action'));
        if (!in_array($action, ['connect','disconnect'], true)) {
            return response()->json(['message' => 'Invalid action'], 400);
        }
        $information  = $request->input('information');
         // Parse query string
        parse_str(parse_url($information, PHP_URL_QUERY), $query);

        $locationId = null;

        // Extract `state` and decode it
        if (isset($query['state'])) {
            $decoded = base64_decode($query['state'], true);

            if ($decoded !== false) {
                $json = json_decode($decoded, true);
                if (is_array($json) && isset($json['id'])) {
                    $locationId = $json['id'];
                }
            }
        }
        // todo git locationId
        if (!$locationId) {
            Log::error('Could not extract locationId from information parameter', [
                'information' => $information,
                'action' => $action,
                'request_url' => $request->fullUrl()
            ]);
            
            return response()->json([
                'message' => 'Could not extract locationId from URL. Please ensure you are accessing this page from the correct integration flow.',
                'error' => 'Invalid locationId',
                'information_param' => $information
            ], 400);
        }

        // 2) Find the user who previously connected this location
        $user = User::where('lead_location_id', $locationId)->first();
        if (!$user) {
            Log::warning('No user found for locationId in connectOrDisconnect', [
                'locationId' => $locationId,
                'action' => $action,
                'request_url' => $request->fullUrl()
            ]);
            
            return response()->json([
                'message' => 'No user found for this location. Please complete the OAuth integration first by visiting /connect with proper authorization code.',
                'locationId' => $locationId,
                'error' => 'User not found - OAuth integration required',
                'solution' => 'Complete OAuth flow first via /connect endpoint'
            ], 404);
        }

        $accessToken = $user->lead_access_token;
        $refreshToken = $user->lead_refresh_token;
        $expiresAt = $user->lead_token_expires_at;

        if (!$accessToken || ($expiresAt && now()->gte($expiresAt))) {
            if (!$refreshToken) {
                return response()->json([
                    'message' => 'Stored token expired and no refresh_token available. Re-connect required.',
                ], 401);
            }

            $new = $this->refreshLeadConnectorToken($refreshToken);
            if (!($new['access_token'] ?? null)) {
                return response()->json([
                    'message' => 'Token refresh failed',
                    'error'   => $new,
                ], 502);
            }

            // Persist refreshed tokens
            $user->lead_access_token     = $new['access_token'];
            $user->lead_refresh_token    = $new['refresh_token'] ?? $refreshToken;
            $user->lead_expires_in       = $new['expires_in'] ?? null;
            $user->lead_token_expires_at = isset($new['expires_in']) ? now()->addSeconds((int)$new['expires_in']) : null;
            $user->save();

            $accessToken = $user->lead_access_token;
        }

        // 4) Validate inputs if action=connect
        if ($action === 'connect') {
            $request->validate([
                'live_apiKey'          => ['required_without:test_apiKey', 'string'],
                'live_publishableKey'  => ['required_without:test_publishableKey', 'string'],
                'test_apiKey'          => ['required_without:live_apiKey', 'string'],
                'test_publishableKey'  => ['required_without:live_publishableKey', 'string'],
            ]);

            // Save API keys to user
            if ($request->has('live_apiKey')) {
                $user->lead_live_api_key = $request->input('live_apiKey');
            }
            if ($request->has('live_publishableKey')) {
                $user->lead_live_publishable_key = $request->input('live_publishableKey');
            }
            if ($request->has('test_apiKey')) {
                $user->lead_test_api_key = $request->input('test_apiKey');
            }
            if ($request->has('test_publishableKey')) {
                $user->lead_test_publishable_key = $request->input('test_publishableKey');
            }
            $user->save();
        }

        $baseUrl = 'https://services.leadconnectorhq.com/payments/custom-provider';
        $qs      = '?locationId=' . urlencode($locationId);

        if ($action === 'connect') {
            $connectUrl = $baseUrl . '/connect' . $qs;

            $payload = [
                // include provider meta if your flow needs it (these are examples)
                'name'        => 'Tap Integration',
                'description' => 'Supports Visa and MasterCard payments via Tap Card SDK, with secure token generation for each transaction. KNET payments redirect customers to the KNET checkout page. The resulting token or KNET source ID is compatible with the Charge API. Note: PayPal is not supported.',
                'paymentsUrl' => 'https://dashboard.mediasolution.io/tap',
                'queryUrl'    => 'https://dashboard.mediasolution.io/api/payment/query',
                'imageUrl'    => 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg',

                'live' => [
                    'apiKey'         => $request->input('live_apiKey'),
                    'publishableKey' => $request->input('live_publishableKey'),
                ],
                'test' => [
                    'apiKey'         => $request->input('test_apiKey'),
                    'publishableKey' => $request->input('test_publishableKey'),
                ],
            ];

            $resp = Http::timeout(25)
                ->acceptJson()
                ->withoutRedirecting()
                ->withToken($accessToken)
                ->withHeaders(['Version' => '2021-07-28'])
                ->post($connectUrl, $payload);

            if ($resp->status() >= 300 && $resp->status() < 400) {
                return response()->json([
                    'message'  => 'LeadConnector /connect returned redirect (blocked)',
                    'status'   => $resp->status(),
                    'location' => $resp->header('Location'),
                    'body'     => $resp->body(),
                ], 502);
            }
            if ($resp->failed()) {
                Log::warning('LeadConnector connect error', ['status' => $resp->status(), 'body' => $resp->json()]);
                return response()->json([
                    'message' => 'LeadConnector connect failed',
                    'status'  => $resp->status(),
                    'error'   => $resp->json() ?: $resp->body(),
                ], 502);
            }

            // Redirect to MediaSolution integration page after successful connection
            $redirectUrl = "https://app.mediasolution.io/integration?selectedTab=installedApps";
            
            return redirect($redirectUrl)->with([
                'api_response' => [
                    'message'      => 'Provider config created/updated successfully',
                    'locationId'   => $locationId,
                    'data'         => $resp->json(),
                    'redirect_url' => $redirectUrl
                ]
            ]);
        }

        // disconnect
        $disconnectUrl = $baseUrl . '/disconnect' . $qs;

        // Get the disconnect mode from the new radio button selection
        $disconnectMode = $request->input('disconnect_mode', 'test'); // default to test mode
        
         $payload = [
                // include provider meta if your flow needs it (these are examples)
                'liveMode'        => $disconnectMode === 'live' ? true : false,
         ];
        $resp = Http::timeout(20)
            ->acceptJson()
            ->withoutRedirecting()
            ->withToken($accessToken)
            ->withHeaders(['Version' => '2021-07-28'])
            ->post($disconnectUrl, $payload);

        if ($resp->status() >= 300 && $resp->status() < 400) {
            return response()->json([
                'message'  => 'LeadConnector /disconnect returned redirect (blocked)',
                'status'   => $resp->status(),
                'location' => $resp->header('Location'),
                'body'     => $resp->body(),
            ], 502);
        }
        if ($resp->failed()) {
            Log::warning('LeadConnector disconnect error', ['status' => $resp->status(), 'body' => $resp->json()]);
            return response()->json([
                'message' => 'LeadConnector disconnect failed',
                'status'  => $resp->status(),
                'error'   => $resp->json() ?: $resp->body(),
            ], 502);
        }

        return response()->json([
            'message'      => 'Provider config disconnected',
            'locationId'   => $locationId,
            'disconnect_mode' => $disconnectMode,
            'data'         => $resp->json(),
        ]);
    }

        /**
         * Refresh helper using stored refresh_token
         */
        private function refreshLeadConnectorToken(string $refreshToken): array
        {
            $tokenUrl = config('services.external_auth.token_url', 'https://services.leadconnectorhq.com/oauth/token');

            $resp = Http::timeout(15)
                ->acceptJson()
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type'    => 'refresh_token',
                    'client_id'     => config('services.external_auth.client_id'),
                    'client_secret' => config('services.external_auth.client_secret'),
                    'refresh_token' => $refreshToken,
                ]);

            if ($resp->failed()) {
                Log::warning('Token refresh failed', ['status' => $resp->status(), 'body' => $resp->json()]);
                return [];
            }
            return $resp->json() ?? [];
        }

        public function webhook(Request $request)
        {
            Log::info('GoHighLevel webhook received', ['request' => $request->all()]);
            
            $event = $request->input('event');
            $locationId = $request->input('locationId');
            $apiKey = $request->input('apiKey');
            
            // Find user by location ID
            $user = User::where('lead_location_id', $locationId)->first();
            if (!$user) {
                Log::warning('User not found for location', ['locationId' => $locationId]);
                return response()->json(['message' => 'User not found'], 404);
            }
            
            // Handle different webhook events
            switch ($event) {
                case 'subscription.charged':
                    $this->handleSubscriptionCharged($request, $user);
                    break;
                case 'subscription.trialing':
                    $this->handleSubscriptionTrialing($request, $user);
                    break;
                case 'subscription.active':
                    $this->handleSubscriptionActive($request, $user);
                    break;
                case 'subscription.updated':
                    $this->handleSubscriptionUpdated($request, $user);
                    break;
                case 'payment.captured':
                    $this->handlePaymentCaptured($request, $user);
                    break;
                default:
                    Log::info('Unhandled webhook event', ['event' => $event]);
            }
            
            return response()->json(['message' => 'Webhook processed']);
        }
        
        private function handleSubscriptionCharged(Request $request, User $user)
        {
            Log::info('Subscription charged', [
                'subscriptionId' => $request->input('ghlSubscriptionId'),
                'chargeId' => $request->input('chargeId'),
                'amount' => $request->input('chargeSnapshot.amount')
            ]);
        }
        
        private function handleSubscriptionTrialing(Request $request, User $user)
        {
            Log::info('Subscription trialing', [
                'subscriptionId' => $request->input('ghlSubscriptionId')
            ]);
        }
        
        private function handleSubscriptionActive(Request $request, User $user)
        {
            Log::info('Subscription active', [
                'subscriptionId' => $request->input('ghlSubscriptionId')
            ]);
        }
        
        private function handleSubscriptionUpdated(Request $request, User $user)
        {
            Log::info('Subscription updated', [
                'subscriptionId' => $request->input('ghlSubscriptionId')
            ]);
        }
        
        private function handlePaymentCaptured(Request $request, User $user)
        {
            Log::info('Payment captured', [
                'chargeId' => $request->input('chargeId'),
                'transactionId' => $request->input('ghlTransactionId')
            ]);
        }

        /**
         * Create a charge using src_all (all payment methods)
         * This replaces the Card SDK approach with a hosted payment page
         */
        public function createCharge(Request $request)
        {
            try {
                // Get payment data from GHL
                $amount = $request->input('amount');
                $currency = $request->input('currency', 'JOD');
                $customer = $request->input('customer');
                $description = $request->input('description');
                $orderId = $request->input('orderId');
                $transactionId = $request->input('transactionId');
                $locationId = $request->input('locationId');

                // Find user by location ID
                $user = User::where('lead_location_id', $locationId)->first();
                if (!$user) {
                    return response()->json(['message' => 'User not found'], 404);
                }

                // Get API keys from user
                $apiKey = $user->lead_test_api_key ?? $user->lead_live_api_key;
                $publishableKey = $user->lead_test_publishable_key ?? $user->lead_live_publishable_key;

                if (!$apiKey || !$publishableKey) {
                    return response()->json(['message' => 'API keys not configured'], 400);
                }

                // Initialize Tap service
                $tapService = new \App\Services\TapPaymentService($apiKey, $publishableKey);

                // Create charge with src_all
                $chargeResponse = $tapService->createChargeWithAllPaymentMethods(
                    $amount,
                    $currency,
                    $customer,
                    $description,
                    $orderId,
                    $transactionId
                );

                if (!$chargeResponse) {
                    return response()->json(['message' => 'Failed to create charge'], 500);
                }

                // Return the charge response with redirect URL
                return response()->json([
                    'success' => true,
                    'charge' => $chargeResponse,
                    'redirect_url' => $chargeResponse['transaction']['url'] ?? null
                ]);

            } catch (\Exception $e) {
                Log::error('Charge creation failed', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'Charge creation failed'], 500);
            }
        }

    /**
     * Create a charge using Tap Payments API
     */
    public function createTapCharge(Request $request)
    {
        try {
            Log::info('Tap charge creation request received', ['data' => $request->all()]);
            Log::info('Request method: ' . $request->method());
            Log::info('Request URL: ' . $request->fullUrl());
            
            // CORS headers will be handled by Laravel's built-in middleware
            
            $data = $request->all();
            
            // Prepare the Tap API request using exact format from documentation
            $tapData = [
                'amount' => $data['amount'] ?? 1,
                'currency' => $data['currency'] ?? 'KWD',
                'customer_initiated' => $data['customer_initiated'] ?? true,
                'threeDSecure' => $data['threeDSecure'] ?? true,
                'save_card' => $data['save_card'] ?? false,
                'description' => $data['description'] ?? 'Test Description',
                'metadata' => $data['metadata'] ?? ['udf1' => 'Metadata 1'],
                'receipt' => $data['receipt'] ?? ['email' => false, 'sms' => false],
                'reference' => $data['reference'] ?? ['transaction' => 'txn_01', 'order' => 'ord_01'],
                'customer' => $data['customer'] ?? [
                    'first_name' => 'test',
                    'middle_name' => 'test', 
                    'last_name' => 'test',
                    'email' => 'test@test.com',
                    'phone' => ['country_code' => 965, 'number' => 51234567]
                ],
                'merchant' => $data['merchant'] ?? ['id' => '1234'],
                'source' => $data['source'] ?? ['id' => 'src_all'], // Use src_all for all payment methods
                'post' => $data['post'] ?? ['url' => config('app.url') . '/charge/webhook'],
                'redirect' => $data['redirect'] ?? ['url' => config('app.url') . '/charge/redirect']
            ];

            // Get user and API keys based on locationId
            $locationId = $data['merchant']['id'] ?? null;
            $user = User::where('lead_location_id', $locationId)->first();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for locationId: ' . $locationId
                ], 404)->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Use the secret key based on the user's stored tap_mode
            $secretKey = $user->tap_mode === 'live' ? $user->lead_live_secret_key : $user->lead_test_secret_key;
            $isLive = $user->tap_mode === 'live';

            if (!$secretKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Secret key not configured for ' . $user->tap_mode . ' mode'
                ], 500)->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Call Tap Payments API using exact format from documentation
            Log::info('Calling Tap API with data', ['tapData' => $tapData]);
            
            // Convert to JSON string like the PHP example
            $jsonBody = json_encode($tapData);
            Log::info('JSON body being sent', ['jsonBody' => $jsonBody]);
            
            Log::info('API key debug for charge creation', [
                'locationId' => $locationId,
                'user_tap_mode' => $user->tap_mode,
                'secret_key_prefix' => substr($secretKey, 0, 15) . '...',
                'is_live' => $isLive,
                'has_test_key' => !empty($user->lead_test_secret_key),
                'has_live_key' => !empty($user->lead_live_secret_key)
            ]);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'accept' => 'application/json',
                'content-type' => 'application/json'
            ])->withBody($jsonBody, 'application/json')
              ->post('https://api.tap.company/v2/charges/');
            
            Log::info('Tap API response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $chargeData = $response->json();
                
                // Store charge ID in session for popup payment flow
                if (isset($chargeData['id'])) {
                    session(['last_charge_id' => $chargeData['id']]);
                    session(['user_id' => $user->id]);
                }
                
                return response()->json([
                    'success' => true,
                    'charge' => $chargeData,
                    'message' => 'Charge created successfully'
                ])->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create charge with Tap',
                    'error' => $response->json()
                ], $response->status())->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

        } catch (\Exception $e) {
            Log::error('Tap charge creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the charge'
            ], 500)->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Retrieve charge status from Tap API
     */
    public function getChargeStatus(Request $request)
    {
        try {
            $tapId = $request->input('tap_id');
            $locationId = $request->input('locationId');
            
            if (!$tapId) {
                return response()->json([
                    'success' => false,
                    'message' => 'tap_id parameter is required'
                ], 400)->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Find user by location ID (same as createCharge method)
            $user = User::where('lead_location_id', $locationId)->first();
            
            Log::info('Charge status request debug', [
                'tap_id' => $tapId,
                'locationId' => $locationId,
                'user_found' => $user ? true : false,
                'user_id' => $user ? $user->id : null,
                'user_location_id' => $user ? $user->lead_location_id : null
            ]);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for locationId: ' . $locationId
                ], 404)->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            // Use the secret key based on the user's stored tap_mode (same as createCharge)
            $secretKey = $user->tap_mode === 'live' ? $user->lead_live_secret_key : $user->lead_test_secret_key;
            $isLive = $user->tap_mode === 'live';

            if (!$secretKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Secret key not configured for ' . $user->tap_mode . ' mode'
                ], 500)->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            Log::info('API key debug for charge retrieval', [
                'tap_id' => $tapId,
                'user_tap_mode' => $user->tap_mode,
                'secret_key_prefix' => substr($secretKey, 0, 15) . '...',
                'is_live' => $isLive,
                'has_test_key' => !empty($user->lead_test_secret_key),
                'has_live_key' => !empty($user->lead_live_secret_key)
            ]);

            // Call Tap API with correct secret key (same as createCharge)
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'accept' => 'application/json',
            ])->get('https://api.tap.company/v2/charges/' . $tapId);

            if (!$response->successful()) {
                Log::error('Tap charge retrieval failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve charge from Tap API'
                ], 500)->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }

            $chargeData = $response->json();
            
            // Log the charge status for debugging
            Log::info('Charge status retrieved', [
                'tap_id' => $tapId,
                'status' => $chargeData['status'] ?? 'unknown',
                'amount' => $chargeData['amount'] ?? 0,
                'currency' => $chargeData['currency'] ?? 'unknown',
                'response_code' => $chargeData['response']['code'] ?? 'unknown',
                'response_message' => $chargeData['response']['message'] ?? 'unknown'
            ]);
            
            // Determine payment status based on charge status
            $paymentStatus = $this->determinePaymentStatus($chargeData);
            
            return response()->json([
                'success' => true,
                'charge' => $chargeData,
                'payment_status' => $paymentStatus['status'],
                'message' => $paymentStatus['message'],
                'is_successful' => $paymentStatus['is_successful'],
                'amount' => $chargeData['amount'] ?? 0,
                'currency' => $chargeData['currency'] ?? 'USD',
                'transaction_id' => $chargeData['reference']['transaction'] ?? null,
                'order_id' => $chargeData['reference']['order'] ?? null
            ])->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');

        } catch (\Exception $e) {
            Log::error('Charge status retrieval failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving charge status'
            ], 500)->header('Access-Control-Allow-Origin', '*')
              ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
              ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        }
    }

    /**
     * Determine payment status based on Tap charge data
     */
    private function determinePaymentStatus($chargeData)
    {
        $status = $chargeData['status'] ?? 'UNKNOWN';
        $responseCode = $chargeData['response']['code'] ?? 'unknown';
        $responseMessage = $chargeData['response']['message'] ?? 'unknown';
        
        switch ($status) {
            case 'CAPTURED':
                return [
                    'status' => 'success',
                    'message' => 'Payment completed successfully',
                    'is_successful' => true
                ];
                
            case 'AUTHORIZED':
                return [
                    'status' => 'authorized',
                    'message' => 'Payment authorized but not yet captured',
                    'is_successful' => true
                ];
                
            case 'INITIATED':
                return [
                    'status' => 'pending',
                    'message' => 'Payment is being processed',
                    'is_successful' => false
                ];
                
            case 'FAILED':
                return [
                    'status' => 'failed',
                    'message' => 'Payment failed: ' . $responseMessage,
                    'is_successful' => false
                ];
                
            case 'CANCELLED':
                return [
                    'status' => 'cancelled',
                    'message' => 'Payment was cancelled',
                    'is_successful' => false
                ];
                
            case 'DECLINED':
                return [
                    'status' => 'declined',
                    'message' => 'Payment was declined: ' . $responseMessage,
                    'is_successful' => false
                ];
                
            case 'REVERSED':
                return [
                    'status' => 'reversed',
                    'message' => 'Payment was reversed',
                    'is_successful' => false
                ];
                
            default:
                return [
                    'status' => 'unknown',
                    'message' => 'Payment status unknown: ' . $status,
                    'is_successful' => false
                ];
        }
    }

    /**
     * Verify payment for GoHighLevel integration
     * This endpoint is called by GHL to verify if a payment was successful
     */
    public function verifyPayment(Request $request)
    {
         try {
             $data = $request->all();
            Log::info('Payment verification request received', [
                'type' => $data['type'] ?? 'unknown',
                'transactionId' => $data['transactionId'] ?? 'unknown',
                'chargeId' => $data['chargeId'] ?? 'unknown',
                'apiKey' => $data['apiKey'] ?? 'unknown',
                'subscriptionId' => $data['subscriptionId'] ?? null
            ]);

            // Validate required fields
            if (!isset($data['type']) || $data['type'] !== 'verify') {
                return response()->json([
                    'failed' => true,
                    'message' => 'Invalid verification request type'
                ]);
            }

            if (!isset($data['transactionId']) || !isset($data['chargeId'])) {
                return response()->json([
                    'failed' => true,
                    'message' => 'Missing required fields: transactionId or chargeId'
                ]);
            }

            $transactionId = $data['transactionId'];
            $chargeId = $data['chargeId'];
            $apiKey = $data['apiKey'] ?? null;

            // Find user by API key or transaction ID
            $user = null;
            if ($apiKey) {
                $user = User::where('lead_test_api_key', $apiKey)
                          ->orWhere('lead_live_api_key', $apiKey)
                          ->first();
            }

            if (!$user) {
                // Try to find user by transaction ID from charge metadata
                // This is a fallback method
                Log::warning('User not found by API key, attempting to verify by charge ID', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId
                ]);
            }

            // Verify the charge with Tap API
            $isPaymentSuccessful = $this->verifyChargeWithTap($chargeId, $user);

            if ($isPaymentSuccessful) {
                Log::info('Payment verification successful', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId
                ]);

                return response()->json([
                    'success' => true
                ]);
            } else {
                Log::warning('Payment verification failed', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId
                ]);

                return response()->json([
                    'failed' => true
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Payment verification error', [
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);

            return response()->json([
                'failed' => true,
                'message' => 'Verification failed due to server error'
            ]);
        }
    }

    /**
     * Verify charge with Tap API
     */
    private function verifyChargeWithTap($chargeId, $user = null)
    {
        try {
            // If no user provided, we can't verify with Tap API
            if (!$user) {
                Log::warning('Cannot verify charge without user context', ['chargeId' => $chargeId]);
                return false;
            }

            // Use the secret key based on the user's stored tap_mode
            $secretKey = $user->tap_mode === 'live' ? $user->lead_live_secret_key : $user->lead_test_secret_key;

            if (!$secretKey) {
                Log::error('No secret key available for user', ['userId' => $user->id, 'tap_mode' => $user->tap_mode]);
                return false;
            }

            // Call Tap API to get charge details
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'accept' => 'application/json',
            ])->get('https://api.tap.company/v2/charges/' . $chargeId);

            if (!$response->successful()) {
                Log::error('Tap API verification failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return false;
            }

            $chargeData = $response->json();
            $status = $chargeData['status'] ?? 'UNKNOWN';

            Log::info('Tap charge verification result', [
                'chargeId' => $chargeId,
                'status' => $status,
                'response_code' => $chargeData['response']['code'] ?? 'unknown'
            ]);

            // Consider payment successful if status is CAPTURED or AUTHORIZED
            return in_array($status, ['CAPTURED', 'AUTHORIZED']);

        } catch (\Exception $e) {
            Log::error('Error verifying charge with Tap API', [
                'chargeId' => $chargeId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get the last charge status for popup payment flow
     */
    public function getLastChargeStatus(Request $request)
    {
        try {
            Log::info('getLastChargeStatus called', [
                'session_id' => session()->getId(),
                'all_session' => session()->all()
            ]);
            
            // Get the most recent charge from session or database
            $lastChargeId = session('last_charge_id');
            
            Log::info('Last charge ID from session', ['last_charge_id' => $lastChargeId]);
            
            if (!$lastChargeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No recent charge found'
                ], 404);
            }

            // Get user from session
            $userId = session('user_id');
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $user = User::find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Use the secret key based on the user's stored tap_mode
            $secretKey = $user->tap_mode === 'live' ? $user->lead_live_secret_key : $user->lead_test_secret_key;

            if (!$secretKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'No API key available'
                ], 400);
            }

            // Call Tap API to get charge details
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $secretKey,
                'accept' => 'application/json',
            ])->get('https://api.tap.company/v2/charges/' . $lastChargeId);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve charge status',
                    'error' => $response->json()
                ], 400);
            }

            $chargeData = $response->json();
            
            return response()->json([
                'success' => true,
                'charge' => $chargeData
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting last charge status', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }
}
