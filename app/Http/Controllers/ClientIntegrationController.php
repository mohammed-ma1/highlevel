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
            'name'        => 'Tap Payments',
            'description' => 'Innovating payment acceptance & collection in MENA',
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

            // Redirect to GHL integrations page after successful connection
            $redirectUrl = "https://app.gohighlevel.com/v2/location/{$locationId}/payments/integrations";
            
            Log::info('Redirecting to GHL integrations page', [
                'locationId' => $locationId,
                'redirectUrl' => $redirectUrl,
                'user_id' => $user->id,
            ]);

            return redirect($redirectUrl);

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
                'merchant_id'          => ['required', 'string'],
                'apiKey'               => ['required', 'string'],
                'tap_mode'             => ['required', 'in:test,live'],
                'live_publishableKey'  => ['required_without:test_publishableKey', 'string'],
                'live_secretKey'       => ['nullable', 'string'],
                'test_publishableKey'  => ['required_without:live_publishableKey', 'string'],
                'test_secretKey'       => ['nullable', 'string'],
            ]);

            // Save merchant ID
            $user->tap_merchant_id = $request->input('merchant_id');
            
            // Save tap mode (test or live)
            $user->tap_mode = $request->input('tap_mode', 'test');
            
            // Save API key (use same for both test and live)
            $apiKey = $request->input('apiKey');
            $user->lead_live_api_key = $apiKey;
            $user->lead_test_api_key = $apiKey;
            
            // Save publishable keys
            if ($request->has('live_publishableKey')) {
                $user->lead_live_publishable_key = $request->input('live_publishableKey');
            }
            if ($request->has('test_publishableKey')) {
                $user->lead_test_publishable_key = $request->input('test_publishableKey');
            }
            
            // Save secret keys
            if ($request->has('live_secretKey')) {
                $user->lead_live_secret_key = $request->input('live_secretKey');
            }
            if ($request->has('test_secretKey')) {
                $user->lead_test_secret_key = $request->input('test_secretKey');
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
                    'apiKey'         => $request->input('apiKey'),
                    'publishableKey' => $request->input('live_publishableKey'),
                ],
                'test' => [
                    'apiKey'         => $request->input('apiKey'),
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

            // Return success message instead of redirecting
            return redirect()->back()->with([
                'success' => true,
                'message' => 'Provider config created/updated successfully',
                'locationId' => $locationId,
                'api_response' => $resp->json()
            ])->withInput($request->only('information'));
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

        /**
         * Handle webhook events from GoHighLevel
         * Endpoint: https://backend.leadconnectorhq.com/payments/custom-provider/webhook
         * 
         * Supported events:
         * - payment.captured
         * - subscription.charged
         * - subscription.trialing
         * - subscription.active
         * - subscription.updated
         */
        public function webhook(Request $request)
        {
            try {
                Log::info('GoHighLevel webhook received', [
                    'event' => $request->input('event'),
                    'locationId' => $request->input('locationId'),
                    'request_data' => $request->all()
                ]);
                
                $event = $request->input('event');
                $locationId = $request->input('locationId');
                $apiKey = $request->input('apiKey');
                
                // Validate required fields for all events
                if (!$event) {
                    Log::warning('Webhook missing event field', ['request' => $request->all()]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Event field is required'
                    ], 400);
                }
                
                if (!$locationId) {
                    Log::warning('Webhook missing locationId', ['event' => $event]);
                    return response()->json([
                        'success' => false,
                        'message' => 'locationId is required'
                    ], 400);
                }
                
                if (!$apiKey) {
                    Log::warning('Webhook missing apiKey', ['event' => $event, 'locationId' => $locationId]);
                    return response()->json([
                        'success' => false,
                        'message' => 'apiKey is required'
                    ], 400);
                }
                
                // Find user by location ID
                $user = User::where('lead_location_id', $locationId)->first();
                if (!$user) {
                    Log::warning('User not found for location', [
                        'locationId' => $locationId,
                        'event' => $event
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found for locationId: ' . $locationId
                    ], 404);
                }
                
                // Validate API key matches user's configured key
                $userApiKey = $user->tap_mode === 'live' ? $user->lead_live_api_key : $user->lead_test_api_key;
                if ($apiKey !== $userApiKey) {
                    Log::warning('API key validation failed for webhook', [
                        'locationId' => $locationId,
                        'event' => $event,
                        'provided_key_length' => strlen($apiKey ?? ''),
                        'expected_key_length' => strlen($userApiKey ?? '')
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid API key'
                    ], 401);
                }
                
                // Validate event-specific required fields and handle event
                $validationResult = $this->validateWebhookEvent($request, $event);
                if (!$validationResult['valid']) {
                    Log::warning('Webhook validation failed', [
                        'event' => $event,
                        'errors' => $validationResult['errors']
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validationResult['errors']
                    ], 400);
                }
                
                // Handle different webhook events
                switch ($event) {
                    case 'payment.captured':
                        $this->handlePaymentCaptured($request, $user);
                        break;
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
                    default:
                        Log::warning('Unhandled webhook event', ['event' => $event]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Unhandled event type: ' . $event
                        ], 400);
                }
                
                Log::info('Webhook processed successfully', [
                    'event' => $event,
                    'locationId' => $locationId
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook processed successfully',
                    'event' => $event
                ]);
                
            } catch (\Exception $e) {
                Log::error('Webhook processing error', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'request_data' => $request->all()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Internal server error processing webhook'
                ], 500);
            }
        }
        
        /**
         * Validate webhook event payload based on event type
         */
        private function validateWebhookEvent(Request $request, string $event): array
        {
            $errors = [];
            
            switch ($event) {
                case 'payment.captured':
                    // Required: event, chargeId, ghlTransactionId, chargeSnapshot, locationId, apiKey
                    if (!$request->has('chargeId')) {
                        $errors[] = 'chargeId is required for payment.captured event';
                    }
                    if (!$request->has('ghlTransactionId')) {
                        $errors[] = 'ghlTransactionId is required for payment.captured event';
                    }
                    if (!$request->has('chargeSnapshot')) {
                        $errors[] = 'chargeSnapshot is required for payment.captured event';
                    }
                    break;
                    
                case 'subscription.updated':
                    // Required: event, ghlSubscriptionId, subscriptionSnapshot, locationId, apiKey
                    if (!$request->has('ghlSubscriptionId')) {
                        $errors[] = 'ghlSubscriptionId is required for subscription.updated event';
                    }
                    if (!$request->has('subscriptionSnapshot')) {
                        $errors[] = 'subscriptionSnapshot is required for subscription.updated event';
                    }
                    break;
                    
                case 'subscription.trialing':
                case 'subscription.active':
                    // Required: event, chargeId, ghlTransactionId, ghlSubscriptionId, marketplaceAppId, locationId, apiKey
                    if (!$request->has('chargeId')) {
                        $errors[] = 'chargeId is required for ' . $event . ' event';
                    }
                    if (!$request->has('ghlTransactionId')) {
                        $errors[] = 'ghlTransactionId is required for ' . $event . ' event';
                    }
                    if (!$request->has('ghlSubscriptionId')) {
                        $errors[] = 'ghlSubscriptionId is required for ' . $event . ' event';
                    }
                    if (!$request->has('marketplaceAppId')) {
                        $errors[] = 'marketplaceAppId is required for ' . $event . ' event';
                    }
                    break;
                    
                case 'subscription.charged':
                    // Required: event, chargeId, ghlSubscriptionId, subscriptionSnapshot, chargeSnapshot, locationId, apiKey
                    if (!$request->has('chargeId')) {
                        $errors[] = 'chargeId is required for subscription.charged event';
                    }
                    if (!$request->has('ghlSubscriptionId')) {
                        $errors[] = 'ghlSubscriptionId is required for subscription.charged event';
                    }
                    if (!$request->has('subscriptionSnapshot')) {
                        $errors[] = 'subscriptionSnapshot is required for subscription.charged event';
                    }
                    if (!$request->has('chargeSnapshot')) {
                        $errors[] = 'chargeSnapshot is required for subscription.charged event';
                    }
                    break;
            }
            
            return [
                'valid' => empty($errors),
                'errors' => $errors
            ];
        }
        
        /**
         * Handle payment.captured event
         * Required fields: event, chargeId, ghlTransactionId, chargeSnapshot, locationId, apiKey
         */
        private function handlePaymentCaptured(Request $request, User $user)
        {
            try {
                $chargeId = $request->input('chargeId');
                $ghlTransactionId = $request->input('ghlTransactionId');
                $chargeSnapshot = $request->input('chargeSnapshot', []);
                $locationId = $request->input('locationId');
                
                Log::info('Processing payment.captured event', [
                    'chargeId' => $chargeId,
                    'ghlTransactionId' => $ghlTransactionId,
                    'locationId' => $locationId,
                    'chargeSnapshot' => $chargeSnapshot
                ]);
                
                // Extract charge details from snapshot
                $amount = $chargeSnapshot['amount'] ?? null;
                $currency = $chargeSnapshot['currency'] ?? null;
                $status = $chargeSnapshot['status'] ?? null;
                
                // Verify the charge with Tap API if chargeId is available
                if ($chargeId) {
                    $isVerified = $this->verifyChargeWithTap($chargeId, $user);
                    Log::info('Charge verification result', [
                        'chargeId' => $chargeId,
                        'verified' => $isVerified
                    ]);
                }
                
                // TODO: Implement your business logic here
                // - Update local database records
                // - Send notifications
                // - Update order status
                // - Trigger other workflows
                
                Log::info('Payment captured event processed successfully', [
                    'chargeId' => $chargeId,
                    'ghlTransactionId' => $ghlTransactionId
                ]);
                
                return ['success' => true];
                
            } catch (\Exception $e) {
                Log::error('Error processing payment.captured event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
        
        /**
         * Handle subscription.charged event
         * Required fields: event, chargeId, ghlSubscriptionId, subscriptionSnapshot, chargeSnapshot, locationId, apiKey
         */
        private function handleSubscriptionCharged(Request $request, User $user)
        {
            try {
                $chargeId = $request->input('chargeId');
                $ghlSubscriptionId = $request->input('ghlSubscriptionId');
                $subscriptionSnapshot = $request->input('subscriptionSnapshot', []);
                $chargeSnapshot = $request->input('chargeSnapshot', []);
                $locationId = $request->input('locationId');
                
                Log::info('Processing subscription.charged event', [
                    'chargeId' => $chargeId,
                    'ghlSubscriptionId' => $ghlSubscriptionId,
                    'locationId' => $locationId,
                    'subscriptionSnapshot' => $subscriptionSnapshot,
                    'chargeSnapshot' => $chargeSnapshot
                ]);
                
                // Extract subscription details
                $subscriptionStatus = $subscriptionSnapshot['status'] ?? null;
                $subscriptionPlan = $subscriptionSnapshot['plan'] ?? null;
                
                // Extract charge details
                $amount = $chargeSnapshot['amount'] ?? null;
                $currency = $chargeSnapshot['currency'] ?? null;
                $chargeStatus = $chargeSnapshot['status'] ?? null;
                
                // Verify the charge with Tap API if chargeId is available
                if ($chargeId) {
                    $isVerified = $this->verifyChargeWithTap($chargeId, $user);
                    Log::info('Subscription charge verification result', [
                        'chargeId' => $chargeId,
                        'verified' => $isVerified
                    ]);
                }
                
                // TODO: Implement your business logic here
                // - Update subscription records
                // - Process recurring payment
                // - Send notifications
                // - Update billing records
                
                Log::info('Subscription charged event processed successfully', [
                    'chargeId' => $chargeId,
                    'ghlSubscriptionId' => $ghlSubscriptionId
                ]);
                
                return ['success' => true];
                
            } catch (\Exception $e) {
                Log::error('Error processing subscription.charged event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
        
        /**
         * Handle subscription.trialing event
         * Required fields: event, chargeId, ghlTransactionId, ghlSubscriptionId, marketplaceAppId, locationId, apiKey
         */
        private function handleSubscriptionTrialing(Request $request, User $user)
        {
            try {
                $chargeId = $request->input('chargeId');
                $ghlTransactionId = $request->input('ghlTransactionId');
                $ghlSubscriptionId = $request->input('ghlSubscriptionId');
                $marketplaceAppId = $request->input('marketplaceAppId');
                $locationId = $request->input('locationId');
                
                Log::info('Processing subscription.trialing event', [
                    'chargeId' => $chargeId,
                    'ghlTransactionId' => $ghlTransactionId,
                    'ghlSubscriptionId' => $ghlSubscriptionId,
                    'marketplaceAppId' => $marketplaceAppId,
                    'locationId' => $locationId
                ]);
                
                // TODO: Implement your business logic here
                // - Activate trial period
                // - Set trial expiration date
                // - Send welcome email
                // - Track trial start
                
                Log::info('Subscription trialing event processed successfully', [
                    'ghlSubscriptionId' => $ghlSubscriptionId
                ]);
                
                return ['success' => true];
                
            } catch (\Exception $e) {
                Log::error('Error processing subscription.trialing event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
        
        /**
         * Handle subscription.active event
         * Required fields: event, chargeId, ghlTransactionId, ghlSubscriptionId, marketplaceAppId, locationId, apiKey
         */
        private function handleSubscriptionActive(Request $request, User $user)
        {
            try {
                $chargeId = $request->input('chargeId');
                $ghlTransactionId = $request->input('ghlTransactionId');
                $ghlSubscriptionId = $request->input('ghlSubscriptionId');
                $marketplaceAppId = $request->input('marketplaceAppId');
                $locationId = $request->input('locationId');
                
                Log::info('Processing subscription.active event', [
                    'chargeId' => $chargeId,
                    'ghlTransactionId' => $ghlTransactionId,
                    'ghlSubscriptionId' => $ghlSubscriptionId,
                    'marketplaceAppId' => $marketplaceAppId,
                    'locationId' => $locationId
                ]);
                
                // TODO: Implement your business logic here
                // - Activate subscription
                // - Enable subscription features
                // - Send activation confirmation
                // - Update subscription status in database
                
                Log::info('Subscription active event processed successfully', [
                    'ghlSubscriptionId' => $ghlSubscriptionId
                ]);
                
                return ['success' => true];
                
            } catch (\Exception $e) {
                Log::error('Error processing subscription.active event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }
        
        /**
         * Handle subscription.updated event
         * Required fields: event, ghlSubscriptionId, subscriptionSnapshot, locationId, apiKey
         */
        private function handleSubscriptionUpdated(Request $request, User $user)
        {
            try {
                $ghlSubscriptionId = $request->input('ghlSubscriptionId');
                $subscriptionSnapshot = $request->input('subscriptionSnapshot', []);
                $locationId = $request->input('locationId');
                
                Log::info('Processing subscription.updated event', [
                    'ghlSubscriptionId' => $ghlSubscriptionId,
                    'locationId' => $locationId,
                    'subscriptionSnapshot' => $subscriptionSnapshot
                ]);
                
                // Extract subscription details
                $status = $subscriptionSnapshot['status'] ?? null;
                $plan = $subscriptionSnapshot['plan'] ?? null;
                $billingCycle = $subscriptionSnapshot['billingCycle'] ?? null;
                $nextBillingDate = $subscriptionSnapshot['nextBillingDate'] ?? null;
                
                // TODO: Implement your business logic here
                // - Update subscription details in database
                // - Handle plan changes
                // - Update billing cycle
                // - Send update notifications
                // - Sync subscription status
                
                Log::info('Subscription updated event processed successfully', [
                    'ghlSubscriptionId' => $ghlSubscriptionId,
                    'status' => $status
                ]);
                
                return ['success' => true];
                
            } catch (\Exception $e) {
                Log::error('Error processing subscription.updated event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        }

        /**
         * Get merchant_id from locationId
         */
        public function getMerchantId(Request $request)
        {
            try {
                $locationId = $request->input('locationId');
                
                if (!$locationId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'locationId is required'
                    ], 400);
                }
                
                $user = User::where('lead_location_id', $locationId)->first();
                
                if (!$user) {
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found for locationId: ' . $locationId
                    ], 404);
                }
                
                if (!$user->tap_merchant_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Merchant ID not configured for this location'
                    ], 404);
                }
                
                return response()->json([
                    'success' => true,
                    'merchant_id' => $user->tap_merchant_id,
                    'tap_mode' => $user->tap_mode,
                    'locationId' => $locationId
                ]);
                
            } catch (\Exception $e) {
                Log::error('Get merchant_id failed', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get merchant_id'
                ], 500);
            }
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
            
            // Get merchant_id from request (optional - only required for live mode)
            // Treat empty strings as null
            $merchantId = !empty($data['merchant']['id']) ? $data['merchant']['id'] : null;
            
            // Try to get locationId from metadata or redirect URL to find user
            $locationId = null;
            if (isset($data['metadata']['udf3'])) {
                // Extract locationId from metadata (format: "Location: {locationId}")
                $udf3 = $data['metadata']['udf3'];
                if (preg_match('/Location:\s*(.+)/', $udf3, $matches)) {
                    $locationId = trim($matches[1]);
                }
            }
            
            // If locationId not in metadata, try to get from redirect URL
            if (!$locationId && isset($data['redirect']['url'])) {
                $redirectUrl = $data['redirect']['url'];
                if (preg_match('/locationId=([^&]+)/', $redirectUrl, $matches)) {
                    $locationId = urldecode($matches[1]);
                }
            }
            
            // Find user - try by merchant_id first, then by locationId
            $user = null;
            if ($merchantId) {
                $user = User::where('tap_merchant_id', $merchantId)->first();
            }
            
            // If user not found by merchant_id, try by locationId
            if (!$user && $locationId) {
                $user = User::where('lead_location_id', $locationId)->first();
            }
            
            // Validate that we found a user
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found. Please provide merchant.id or ensure locationId is in metadata.'
                ], 404)->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }
            
            // Get locationId from user if not already set
            if (!$locationId) {
                $locationId = $user->lead_location_id;
            }
            
            // Check tap_mode - merchant.id is only required for live mode
            $tapMode = $user->tap_mode ?? 'test';
            
            // For live mode: require merchant.id (from request or database)
            if ($tapMode === 'live') {
                // If not provided in request, try to get from database
                if (!$merchantId) {
                    $merchantId = $user->tap_merchant_id;
                }
                
                // If still not available, return error
                if (!$merchantId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'merchant.id is required when tapMode is live'
                    ], 400)->header('Access-Control-Allow-Origin', '*')
                      ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                      ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
                }
            } else {
                // For test mode: try to use merchant.id if available, but don't require it
                if (!$merchantId) {
                    $merchantId = $user->tap_merchant_id;
                }
                // If still not available, we'll proceed without it (Tap API may handle it)
            }

            // Use the secret key based on the user's stored tap_mode
            $secretKey = $user->tap_mode === 'live' ? $user->lead_live_secret_key : $user->lead_test_secret_key;
            $isLive = $user->tap_mode === 'live';

            // Log the secret key
            Log::info('Using secret key for createTapCharge', [
                'merchantId' => $merchantId,
                'locationId' => $locationId,
                'tap_mode' => $user->tap_mode,
                'is_live' => $isLive,
                'secretKey' => $secretKey,
                'secret_key_prefix' => substr($secretKey, 0, 15) . '...',
                'secret_key_length' => strlen($secretKey)
            ]);

            if (!$secretKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Secret key not configured for ' . $user->tap_mode . ' mode'
                ], 500)->header('Access-Control-Allow-Origin', '*')
                  ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                  ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            }
            
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
                'source' => $data['source'] ?? ['id' => 'src_all'], // Use src_all for all payment methods
                'post' => $data['post'] ?? ['url' => config('app.url') . '/charge/webhook'],
                'redirect' => $data['redirect'] ?? ['url' => config('app.url') . '/payment/redirect']
            ];
            
            // Process customer data - handle null values properly
            if (isset($data['customer'])) {
                $customer = $data['customer'];
                // Convert null middle_name to empty string (Tap API doesn't accept null)
                if (isset($customer['middle_name']) && $customer['middle_name'] === null) {
                    $customer['middle_name'] = '';
                }
                // Remove any null values from customer array (but keep empty strings)
                $customer = array_filter($customer, function($value) {
                    return $value !== null;
                }, ARRAY_FILTER_USE_BOTH);
                $tapData['customer'] = $customer;
            } else {
                $tapData['customer'] = [
                    'first_name' => 'test',
                    'middle_name' => '', 
                    'last_name' => 'test',
                    'email' => 'test@test.com',
                    'phone' => ['country_code' => 965, 'number' => 51234567]
                ];
            }
            
            // Only include merchant.id if available (required for live mode, optional for test mode)
            if ($merchantId) {
                $tapData['merchant'] = ['id' => $merchantId];
            }
            
            // Log to verify merchant_id is correct
            Log::info('Tap API request merchant_id', [
                'merchant_id' => $merchantId,
                'tap_mode' => $tapMode,
                'merchant_in_tapData' => $tapData['merchant'] ?? 'not included'
            ]);
            
            // Add locationId to redirect URL so it's available when Tap redirects back
            // Check if locationId already exists in URL to avoid duplicates
            if ($locationId && isset($tapData['redirect']['url'])) {
                $redirectUrl = $tapData['redirect']['url'];
                
                // Check if locationId already exists in the URL
                if (strpos($redirectUrl, 'locationId=') === false) {
                    // locationId not found, add it
                    $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
                    $tapData['redirect']['url'] = $redirectUrl . $separator . 'locationId=' . urlencode($locationId);
                } else {
                    // locationId already exists, replace it to ensure correct value
                    $tapData['redirect']['url'] = preg_replace(
                        '/[?&]locationId=[^&]*/',
                        (strpos($redirectUrl, '?') !== false ? '&' : '?') . 'locationId=' . urlencode($locationId),
                        $redirectUrl,
                        1
                    );
                }
            }

            // Call Tap Payments API using exact format from documentation
            Log::info('Calling Tap API with data', ['tapData' => $tapData]);
            
            // Convert to JSON string like the PHP example
            $jsonBody = json_encode($tapData);
            Log::info('JSON body being sent', ['jsonBody' => $jsonBody]);
            
            Log::info('API key debug for charge creation', [
                'merchantId' => $merchantId,
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
            // Get the most recent charge from session or database
            $lastChargeId = session('last_charge_id');
            
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

    /**
     * Handle payment redirect from Tap after payment completion
     * This processes the payment and triggers webhook logic
     */
    public function handlePaymentRedirect(Request $request)
    {
        try {
            Log::info('Payment redirect received', [
                'query_params' => $request->query(),
                'all_params' => $request->all()
            ]);

            // Get locationId from query parameters
            $locationId = $request->query('locationId');
            $tapId = $request->query('tap_id') ?? $request->query('charge_id');
            $status = $request->query('status');

            // If we have locationId and tapId, process the payment completion
            if ($locationId && $tapId) {
                // Find user by locationId
                $user = User::where('lead_location_id', $locationId)->first();
                
                if ($user) {
                    // Get charge status from Tap API
                    $chargeStatusRequest = new Request([
                        'tap_id' => $tapId,
                        'locationId' => $locationId
                    ]);
                    
                    $chargeStatusResponse = $this->getChargeStatus($chargeStatusRequest);
                    $chargeData = json_decode($chargeStatusResponse->getContent(), true);
                    
                    // If payment is successful, trigger webhook logic
                    if (isset($chargeData['is_successful']) && $chargeData['is_successful']) {
                        Log::info('Payment completed successfully, processing webhook logic', [
                            'chargeId' => $tapId,
                            'locationId' => $locationId,
                            'status' => $chargeData['payment_status'] ?? 'unknown'
                        ]);

                        // Simulate webhook call with payment.captured event
                        // This ensures the webhook logic is executed even if GHL doesn't call it
                        $webhookRequest = new Request([
                            'event' => 'payment.captured',
                            'chargeId' => $tapId,
                            'ghlTransactionId' => $chargeData['transaction_id'] ?? null,
                            'chargeSnapshot' => [
                                'id' => $tapId,
                                'status' => $chargeData['payment_status'] ?? 'CAPTURED',
                                'amount' => $chargeData['amount'] ?? 0,
                                'currency' => $chargeData['currency'] ?? 'KWD',
                                'chargedAt' => time()
                            ],
                            'locationId' => $locationId,
                            'apiKey' => $user->tap_mode === 'live' ? $user->lead_live_api_key : $user->lead_test_api_key
                        ]);

                        // Process the webhook
                        try {
                            $this->webhook($webhookRequest);
                            Log::info('Webhook processed successfully after payment redirect');
                        } catch (\Exception $e) {
                            Log::error('Error processing webhook after payment redirect', [
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                }
            }

            // Return the redirect view (frontend will handle GHL communication)
            return view('payment.redirect', ['data' => $request->all()]);

        } catch (\Exception $e) {
            Log::error('Error handling payment redirect', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Still return the view so frontend can handle it
            return view('payment.redirect', ['data' => $request->all()]);
        }
    }

    /**
     * Handle Tap webhook after payment completion
     * Tap sends webhook to this endpoint when payment status changes
     * This triggers the GHL webhook logic
     */
    public function handleTapWebhook(Request $request)
    {
        try {
            Log::info('Tap webhook received', ['data' => $request->all()]);

            $chargeData = $request->all();
            $chargeId = $chargeData['id'] ?? $chargeData['charge_id'] ?? null;
            $status = $chargeData['status'] ?? null;
            $metadata = $chargeData['metadata'] ?? [];

            // Extract locationId from metadata (udf3 format: "Location: {locationId}")
            $locationId = null;
            if (isset($metadata['udf3'])) {
                $udf3 = $metadata['udf3'];
                if (preg_match('/Location:\s*(.+)/', $udf3, $matches)) {
                    $locationId = trim($matches[1]);
                }
            }

            // If we have locationId and chargeId, and payment is successful, trigger GHL webhook
            if ($locationId && $chargeId && in_array($status, ['CAPTURED', 'AUTHORIZED'])) {
                Log::info('Payment completed via Tap webhook, triggering GHL webhook logic', [
                    'chargeId' => $chargeId,
                    'locationId' => $locationId,
                    'status' => $status
                ]);

                // Find user by locationId
                $user = User::where('lead_location_id', $locationId)->first();
                
                if ($user) {
                    // Extract transaction ID from reference
                    $reference = $chargeData['reference'] ?? [];
                    $ghlTransactionId = $reference['transaction'] ?? null;

                    // Create webhook request for GHL
                    $webhookRequest = new Request([
                        'event' => 'payment.captured',
                        'chargeId' => $chargeId,
                        'ghlTransactionId' => $ghlTransactionId,
                        'chargeSnapshot' => [
                            'id' => $chargeId,
                            'status' => $status,
                            'amount' => $chargeData['amount'] ?? 0,
                            'currency' => $chargeData['currency'] ?? 'KWD',
                            'chargedAt' => isset($chargeData['created']) ? strtotime($chargeData['created']) : time()
                        ],
                        'locationId' => $locationId,
                        'apiKey' => $user->tap_mode === 'live' ? $user->lead_live_api_key : $user->lead_test_api_key
                    ]);

                    // Process the GHL webhook
                    try {
                        $this->webhook($webhookRequest);
                        Log::info('GHL webhook processed successfully after Tap webhook');
                    } catch (\Exception $e) {
                        Log::error('Error processing GHL webhook after Tap webhook', [
                            'error' => $e->getMessage()
                        ]);
                    }
                } else {
                    Log::warning('User not found for locationId in Tap webhook', [
                        'locationId' => $locationId
                    ]);
                }
            }

            // Always return success to Tap
            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Error handling Tap webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Still return success to Tap (don't want Tap to retry)
            return response()->json(['status' => 'success']);
        }
    }
}
