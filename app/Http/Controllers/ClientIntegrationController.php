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
        Log::info('🔵 === CONNECT ENDPOINT CALLED ===', [
            'timestamp' => now()->toIso8601String(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'path' => $request->path(),
            'has_code' => $request->has('code'),
            'code_value' => $request->input('code'),
            'code_length' => $request->input('code') ? strlen($request->input('code')) : 0,
        ]);
        
        // Log all query parameters
        $allQueryParams = $request->query->all();
        Log::info('📋 [REQUEST] All Query Parameters', [
            'query_params' => $allQueryParams,
            'query_params_count' => count($allQueryParams),
            'query_string' => $request->getQueryString(),
        ]);
        
        // Log all request parameters (query + body)
        Log::info('📋 [REQUEST] All Request Parameters', [
            'all_params' => $request->all(),
            'all_params_count' => count($request->all()),
        ]);
        
        // Log headers
        Log::info('📋 [REQUEST] Headers', [
            'referer' => $request->header('referer'),
            'user_agent' => $request->userAgent(),
            'host' => $request->header('host'),
            'origin' => $request->header('origin'),
            'accept' => $request->header('accept'),
            'content_type' => $request->header('content-type'),
            'all_headers' => $request->headers->all(),
        ]);
        
        // Log cookies
        $allCookies = [];
        try {
            // Check if cookies is an object with all() method
            if (is_object($request->cookies) && method_exists($request->cookies, 'all')) {
                $allCookies = $request->cookies->all();
            } elseif (is_array($request->cookies)) {
                // If it's already an array, use it directly
                $allCookies = $request->cookies;
            } else {
                // Fallback: try to get all cookies from headers
                $cookieHeader = $request->header('cookie');
                if ($cookieHeader) {
                    // Parse cookie header manually if needed
                    $allCookies = ['raw_header' => $cookieHeader];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get cookies', ['error' => $e->getMessage()]);
            $allCookies = [];
        }
        
        Log::info('📋 [REQUEST] Cookies', [
            'cookies' => $allCookies,
            'cookies_count' => count($allCookies),
            'has_selected_location_id_cookie' => $request->hasCookie('selected_location_id'),
            'selected_location_id_cookie_value' => $request->cookie('selected_location_id'),
        ]);
        
        // Log session data
        Log::info('📋 [REQUEST] Session Data', [
            'session_id' => session()->getId(),
            'has_selected_location_id_session' => session()->has('selected_location_id'),
            'selected_location_id_session_value' => session('selected_location_id'),
            'all_session_data' => session()->all(),
        ]);
        
        // Check if GHL passes location ID in query parameters (e.g., ?code=xxx&locationId=xxx)
        $locationIdFromQuery = $request->query('locationId') ?? $request->query('location_id') ?? null;
        if ($locationIdFromQuery) {
            Log::info('📍 [REQUEST] Found locationId in query parameters', [
                'locationId' => $locationIdFromQuery,
                'source' => 'query_params',
                'param_name' => $request->query('locationId') ? 'locationId' : 'location_id'
            ]);
        } else {
            Log::info('📍 [REQUEST] No locationId found in query parameters', [
                'checked_params' => ['locationId', 'location_id'],
                'all_query_keys' => array_keys($allQueryParams)
            ]);
        }
        
        // Log full request details summary
        Log::info('📊 [REQUEST] Full Request Summary', [
            'full_url' => $request->fullUrl(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'path' => $request->path(),
            'query_string' => $request->getQueryString(),
            'ip' => $request->ip(),
            'ips' => $request->ips(),
        ]);
        
        // Check if this is a proper OAuth request
        if (!$request->has('code')) {
            Log::info('Connect endpoint called without authorization code - redirecting to OAuth', [
                'url' => $request->fullUrl(),
                'params' => $request->all(),
                'referer' => $request->header('referer')
            ]);
            
            // Extract locationId from referer or URL if available
            // This is important for direct installation links where user selects a specific location
            $selectedLocationId = null;
            $referer = $request->header('referer');
            
            // Try to extract locationId from referer URL (e.g., https://app.gohighlevel.com/v2/location/YAuEX9ihHtdKDKEvbw4a/...)
            if ($referer && preg_match('/\/location\/([^\/]+)/', $referer, $matches)) {
                $selectedLocationId = $matches[1];
                Log::info('📍 Extracted selected locationId from referer (before OAuth)', [
                    'selectedLocationId' => $selectedLocationId,
                    'referer' => $referer
                ]);
            }
            
            // Try to extract from current URL
            if (!$selectedLocationId && preg_match('/\/location\/([^\/]+)/', $request->fullUrl(), $matches)) {
                $selectedLocationId = $matches[1];
                Log::info('📍 Extracted selected locationId from current URL (before OAuth)', [
                    'selectedLocationId' => $selectedLocationId
                ]);
            }
            
            // Store selected location ID in session so we can retrieve it after OAuth callback
            // Also store in a cookie as backup since sessions might not persist across OAuth redirect
            if ($selectedLocationId) {
                session(['selected_location_id' => $selectedLocationId]);
                // Also store in cookie as backup (expires in 10 minutes)
                \Cookie::queue('selected_location_id', $selectedLocationId, 10);
                Log::info('💾 Stored selected locationId in session and cookie', [
                    'selectedLocationId' => $selectedLocationId,
                    'session_id' => session()->getId()
                ]);
            }
            
            // Build OAuth authorization URL
            $clientId = config('services.external_auth.client_id');
            $redirectUri = config('services.external_auth.redirect_uri', 'https://dashboard.mediasolution.io/connect');
            
            // Required scopes for the integration
            $scopes = [
                'payments/orders.write',
                'payments/integration.readonly',
                'payments/integration.write',
                'payments/transactions.readonly',
                'payments/subscriptions.readonly',
                'payments/custom-provider.readonly',
                'payments/custom-provider.write',
                'payments/orders.readonly',
                'products/prices.readonly',
                'products.readonly',
                'invoices.readonly',
                'invoices.write',
                'locations.readonly',
                'payments/orders.collectPayment',
                'oauth.write',
                'oauth.readonly'
            ];
            
            $scopeString = implode(' ', array_map('urlencode', $scopes));
            
            // Build the OAuth URL
            $oauthUrl = 'https://marketplace.gohighlevel.com/oauth/chooselocation?' . http_build_query([
                'response_type' => 'code',
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'scope' => $scopeString,
                'version_id' => $clientId // Using client_id as version_id
            ]);
            
            Log::info('Redirecting to OAuth authorization', [
                'oauth_url' => $oauthUrl,
                'locationId_from_referer' => $locationId
            ]);
            
            // Redirect to OAuth flow
            return redirect($oauthUrl);
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
                ], 502);
            }

            $body = $tokenResponse->json();


            
            Log::info('OAuth response parsed successfully', [
                'has_access_token' => !empty($body['access_token']),
                'has_refresh_token' => !empty($body['refresh_token']),
                'locationId' => $body['locationId'] ?? 'missing',
                'companyId' => $body['companyId'] ?? 'missing',
                'userType' => $body['userType'] ?? 'missing',
                'isBulkInstallation' => $body['isBulkInstallation'] ?? false,
                'all_response_keys' => array_keys($body ?? []),
                'full_response' => $body // Log full response to check for location info
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
            $userType       = $body['userType'] ?? null;    // "Location" or "Company"
            $companyId      = $body['companyId'] ?? null;
            $locationId     = $body['locationId'] ?? null;
            $isBulk         = (bool) ($body['isBulkInstallation'] ?? false);
            $providerUserId = $body['userId'] ?? null;

            // Log initial OAuth response location data
            Log::info('🔍 === LOCATION ID EXTRACTION START ===', [
                'oauth_response_locationId' => $locationId,
                'oauth_response_companyId' => $companyId,
                'oauth_response_userType' => $userType,
                'oauth_response_isBulk' => $isBulk,
                'request_url' => $request->fullUrl(),
                'request_query_params' => $request->query->all(),
                'request_referer' => $request->header('referer'),
                'has_code' => $request->has('code')
            ]);

            // For ALL installations (marketplace and bulk), use the SAME flow:
            // Priority: 1) OAuth response locationId, 2) Query params, 3) Session, 4) Cookie, 5) Referer/URL
            $selectedLocationId = null;
            $extractionSource = null;
            
            // Priority 1: Check OAuth response locationId FIRST (same as marketplace installation)
            // GHL might include locationId in the response even for bulk installations
            if ($locationId && $locationId !== $companyId) {
                // If locationId is different from companyId, it's a specific location
                $selectedLocationId = $locationId;
                $extractionSource = 'oauth_response';
                Log::info('✅ [EXTRACTION] Found locationId in OAuth response (Priority 1 - Highest)', [
                    'selectedLocationId' => $selectedLocationId,
                    'companyId' => $companyId,
                    'source' => $extractionSource,
                    'isBulk' => $isBulk,
                    'userType' => $userType
                ]);
            } elseif ($locationId && $isBulk && $userType === 'Company') {
                // For bulk installations, if locationId equals companyId, we need to find the specific location
                // Continue to fallback methods below
                Log::info('🔍 [EXTRACTION] OAuth response locationId equals companyId - checking fallback sources', [
                    'locationId' => $locationId,
                    'companyId' => $companyId,
                    'isBulk' => $isBulk
                ]);
            }
            
            // For bulk installations, try additional sources if OAuth response didn't provide specific location
            if ($isBulk && $userType === 'Company' && !$selectedLocationId) {
                // Priority 2: Check query parameters (GHL might pass it in redirect URL)
                $queryLocationId = $request->query('locationId') ?? $request->query('location_id') ?? null;
                Log::info('🔍 [EXTRACTION] Checking query parameters', [
                    'locationId_param' => $request->query('locationId'),
                    'location_id_param' => $request->query('location_id'),
                    'found' => !empty($queryLocationId),
                    'value' => $queryLocationId
                ]);
                
                if ($queryLocationId) {
                    $selectedLocationId = $queryLocationId;
                    $extractionSource = 'query_params';
                    Log::info('✅ [EXTRACTION] Found selected locationId in query parameters (Priority 2)', [
                        'selectedLocationId' => $selectedLocationId,
                        'companyId' => $locationId,
                        'source' => $extractionSource
                    ]);
                }
                
                // Priority 3: Try to get from session (stored before OAuth redirect)
                if (!$selectedLocationId) {
                    $sessionLocationId = session('selected_location_id');
                    Log::info('🔍 [EXTRACTION] Checking session', [
                        'session_id' => session()->getId(),
                        'found' => !empty($sessionLocationId),
                        'value' => $sessionLocationId
                    ]);
                    
                    if ($sessionLocationId) {
                        $selectedLocationId = $sessionLocationId;
                        $extractionSource = 'session';
                        Log::info('✅ [EXTRACTION] Retrieved selected locationId from session (Priority 3)', [
                            'selectedLocationId' => $selectedLocationId,
                            'companyId' => $locationId,
                            'source' => $extractionSource,
                            'session_id' => session()->getId()
                        ]);
                        // Clear session after use
                        session()->forget('selected_location_id');
                    }
                }
                
                // Priority 4: Try to get from cookie
                if (!$selectedLocationId) {
                    $cookieLocationId = $request->cookie('selected_location_id');
                    Log::info('🔍 [EXTRACTION] Checking cookie', [
                        'found' => !empty($cookieLocationId),
                        'value' => $cookieLocationId
                    ]);
                    
                    if ($cookieLocationId) {
                        $selectedLocationId = $cookieLocationId;
                        $extractionSource = 'cookie';
                        Log::info('✅ [EXTRACTION] Retrieved selected locationId from cookie (Priority 4)', [
                            'selectedLocationId' => $selectedLocationId,
                            'companyId' => $locationId,
                            'source' => $extractionSource
                        ]);
                        // Clear cookie after use
                        \Cookie::queue(\Cookie::forget('selected_location_id'));
                    }
                }
                
                // Priority 5: Try to extract from referer (might not work after OAuth redirect)
                if (!$selectedLocationId) {
                    $referer = $request->header('referer');
                    $refererLocationId = null;
                    if ($referer && preg_match('/\/location\/([^\/]+)/', $referer, $matches)) {
                        $refererLocationId = $matches[1];
                    }
                    
                    Log::info('🔍 [EXTRACTION] Checking referer', [
                        'referer' => $referer,
                        'found' => !empty($refererLocationId),
                        'value' => $refererLocationId
                    ]);
                    
                    if ($refererLocationId) {
                        $selectedLocationId = $refererLocationId;
                        $extractionSource = 'referer';
                        Log::info('✅ [EXTRACTION] Extracted selected locationId from referer (Priority 5)', [
                            'selectedLocationId' => $selectedLocationId,
                            'companyId' => $locationId,
                            'source' => $extractionSource,
                            'referer' => $referer
                        ]);
                    }
                }
                
                // Priority 6: Also try to extract from current URL
                if (!$selectedLocationId) {
                    $urlLocationId = null;
                    if (preg_match('/\/location\/([^\/]+)/', $request->fullUrl(), $matches)) {
                        $urlLocationId = $matches[1];
                    }
                    
                    Log::info('🔍 [EXTRACTION] Checking current URL', [
                        'url' => $request->fullUrl(),
                        'found' => !empty($urlLocationId),
                        'value' => $urlLocationId
                    ]);
                    
                    if ($urlLocationId) {
                        $selectedLocationId = $urlLocationId;
                        $extractionSource = 'current_url';
                        Log::info('✅ [EXTRACTION] Extracted selected locationId from current URL (Priority 6)', [
                            'selectedLocationId' => $selectedLocationId,
                            'companyId' => $locationId,
                            'source' => $extractionSource
                        ]);
                    }
                }
            }
            
            // Log final extraction result for ALL installations
            Log::info('📊 [EXTRACTION] Final location ID extraction result', [
                'selectedLocationId' => $selectedLocationId,
                'extractionSource' => $extractionSource,
                'oauth_response_locationId' => $locationId,
                'oauth_response_companyId' => $companyId,
                'was_found' => !empty($selectedLocationId),
                'will_use_for_user' => $selectedLocationId ?? $locationId,
                'isBulk' => $isBulk,
                'userType' => $userType
            ]);

            // If locationId is missing, try to extract it from the JWT access token
            // This happens for Company-level bulk installations
            if (!$locationId && $accessToken) {
                $tokenLocationId = $this->extractLocationIdFromToken($accessToken);
                
                Log::info('🔍 [EXTRACTION] Extracting locationId from JWT token', [
                    'token_has_data' => !empty($accessToken),
                    'extracted_location_id' => $tokenLocationId,
                    'userType' => $userType,
                    'isBulk' => $isBulk,
                    'selectedLocationId' => $selectedLocationId ?? null
                ]);
                
                if ($tokenLocationId) {
                    $locationId = $tokenLocationId;
                    Log::info('✅ [EXTRACTION] Successfully extracted locationId from JWT token', [
                        'locationId' => $locationId
                    ]);
                } else {
                    Log::warning('⚠️ [EXTRACTION] Failed to extract locationId from JWT token', [
                        'token_preview' => substr($accessToken, 0, 50) . '...'
                    ]);
                }
            }

            Log::info('Validating OAuth data', [
                'has_access_token' => !empty($accessToken),
                'has_location_id' => !empty($locationId),
                'access_token_length' => $accessToken ? strlen($accessToken) : 0,
                'location_id_value' => $locationId,
                'userType' => $userType,
                'isBulk' => $isBulk
            ]);
            
            if (!$accessToken || !$locationId) {
                Log::error('OAuth validation failed', [
                    'missing_access_token' => empty($accessToken),
                    'missing_location_id' => empty($locationId),
                    'userType' => $userType,
                    'isBulk' => $isBulk,
                    'has_company_id' => !empty($companyId)
                ]);
                return response()->json([
                    'message' => 'Invalid OAuth response (missing access_token or locationId)',
                ], 502);
            }

            // 2) Find or create a local user tied to this location
            // For bulk installations with a selected location, create/update location-specific user
            // Otherwise, use company-level location ID
            $userLocationId = $selectedLocationId ?? $locationId;
            
            Log::info('📋 === LOCATION ID EXTRACTION SUMMARY ===', [
                'oauth_response_locationId' => $locationId,
                'oauth_response_companyId' => $companyId,
                'selectedLocationId' => $selectedLocationId ?? null,
                'extractionSource' => $extractionSource ?? null,
                'userLocationId' => $userLocationId,
                'isBulk' => $isBulk,
                'userType' => $userType,
                'will_create_user_for' => $userLocationId,
                'will_register_provider_for' => $selectedLocationId ?? $locationId
            ]);
            
            Log::info('🔍 Looking for user', [
                'companyId' => $locationId,
                'selectedLocationId' => $selectedLocationId,
                'userLocationId' => $userLocationId,
                'isBulk' => $isBulk,
                'userType' => $userType
            ]);
            
            // Prefer to find by lead_location_id (unique per location)
            $user = User::where('lead_location_id', $userLocationId)->first();
            
            // Fallback: if no user found by location_id, try to find by email pattern
            if (!$user) {
                $baseEmail = "location_{$userLocationId}@leadconnector.local";
                $user = User::where('email', 'like', "location_{$userLocationId}%@leadconnector.local")->first();
                
                if ($user) {
                    Log::info('Found existing user by email pattern', [
                        'userLocationId' => $userLocationId,
                        'user_id' => $user->id,
                        'email' => $user->email
                    ]);
                }
            }

            if (!$user) {
                // No user? Create a minimal one.
                // Generate a unique email to avoid duplicate key errors
                $baseEmail = "location_{$userLocationId}@leadconnector.local";
                $placeholderEmail = $baseEmail;
                $counter = 1;
                
                // Check if email already exists and generate a unique one
                while (User::where('email', $placeholderEmail)->exists()) {
                    $placeholderEmail = "location_{$userLocationId}_{$counter}@leadconnector.local";
                    $counter++;
                }

                Log::info('Creating new user', [
                    'userLocationId' => $userLocationId,
                    'companyId' => $locationId,
                    'selectedLocationId' => $selectedLocationId,
                    'generated_email' => $placeholderEmail,
                    'email_attempts' => $counter,
                    'isBulk' => $isBulk
                ]);

                $user = new User();
                $user->name = $selectedLocationId ? "Location {$selectedLocationId}" : "Location {$locationId}";
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
            // Use selected location ID if available, otherwise use company ID
            $user->lead_location_id           = $userLocationId;
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
                    'error_type' => 'internal_error'
                ], 500);
            }

           // dd($user);

            // Verify user was actually saved
            if (!$user->exists || !$user->id) {
                Log::error('❌ CRITICAL: User was not saved successfully', [
                    'user_exists' => $user->exists,
                'user_id' => $user->id,
                    'locationId' => $locationId,
                    'user_attributes' => $user->getAttributes()
                ]);
                
                return response()->json([
                    'message' => 'Failed to save user - user record was not created',
                    'error_type' => 'user_creation_failed'
                ], 500);
            }
            
            Log::info('✅ User saved successfully', [
                'user_id' => $user->id,
                'locationId' => $locationId,
                'email' => $user->email,
                'is_bulk' => $isBulk,
                'user_type' => $userType
            ]);

            // 4) Associate app ↔ location (provider)
            // NOTE: Provider registration is critical for the integration to appear in GHL
            // But user creation should succeed even if provider registration fails
            // Decode token to verify scopes and locationId
            $tokenData = $this->decodeJWTToken($accessToken);
            
            Log::info('=== TOKEN ANALYSIS ===', [
                'locationId' => $locationId,
                'userType' => $userType,
                'isBulk' => $isBulk,
                'token_has_data' => !empty($tokenData),
                'token_authClass' => $tokenData['authClass'] ?? null,
                'token_authClassId' => $tokenData['authClassId'] ?? null,
                'token_primaryAuthClassId' => $tokenData['primaryAuthClassId'] ?? null,
                'token_scopes' => $tokenData['oauthMeta']['scopes'] ?? null,
                'token_has_custom_provider_write' => !empty($tokenData['oauthMeta']['scopes']) && in_array('payments/custom-provider.write', $tokenData['oauthMeta']['scopes'] ?? []),
                'token_locationId_match' => ($tokenData['primaryAuthClassId'] ?? $tokenData['authClassId'] ?? null) === $locationId,
                'scope_from_response' => $scope,
                'scopes_array' => $scope ? explode(' ', $scope) : null
            ]);

            // Fetch all locations by company ID to check if approved location is in the list
            // This helps us understand which locations are available and if the selected location is among them
            if ($companyId) {
                try {
                    $allLocationsUrl = "https://services.leadconnectorhq.com/locations";
                    
                    Log::info('🔍 Fetching all locations by company ID', [
                        'companyId' => $companyId,
                        'url' => $allLocationsUrl,
                        'api_docs' => 'https://marketplace.gohighlevel.com/docs/ghl/locations/get-locations'
                    ]);
                    
                    $allLocationsResponse = Http::timeout(30)
                        ->acceptJson()
                        ->withToken($accessToken)
                        ->withHeaders(['Version' => '2021-07-28'])
                        ->get($allLocationsUrl, [
                            'companyId' => $companyId,
                            'limit' => 100
                        ]);
                    
                    Log::info('📡 All Locations API response', [
                        'status' => $allLocationsResponse->status(),
                        'successful' => $allLocationsResponse->successful(),
                        'response_keys' => $allLocationsResponse->successful() ? array_keys($allLocationsResponse->json() ?? []) : null,
                        'body_preview' => substr($allLocationsResponse->body(), 0, 500)
                    ]);
                    
                    if ($allLocationsResponse->successful()) {
                        $allLocationsData = $allLocationsResponse->json();
                        $allLocations = $allLocationsData['locations'] ?? $allLocationsData['data'] ?? $allLocationsData ?? [];
                        
                        if (!is_array($allLocations)) {
                            $allLocations = [];
                        }
                        
                        // Extract location IDs from the response
                        $locationIds = [];
                        foreach ($allLocations as $loc) {
                            $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                            if ($locId) {
                                $locationIds[] = $locId;
                            }
                        }
                        
                        // Check if selected location ID is in the list
                        $selectedLocationFound = false;
                        if ($selectedLocationId && in_array($selectedLocationId, $locationIds)) {
                            $selectedLocationFound = true;
                        }
                        
                        Log::info('📋 All Locations by Company ID - Full Response', [
                            'companyId' => $companyId,
                            'total_locations_count' => count($allLocations),
                            'location_ids' => $locationIds,
                            'selectedLocationId' => $selectedLocationId ?? null,
                            'selectedLocationFound' => $selectedLocationFound,
                            'all_locations' => array_map(function($loc) {
                                return [
                                    'id' => $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null,
                                    'name' => $loc['name'] ?? $loc['locationName'] ?? null,
                                    'address' => $loc['address'] ?? null,
                                    'companyId' => $loc['companyId'] ?? null,
                                    'full_data' => $loc
                                ];
                            }, $allLocations),
                            'full_response' => $allLocationsData
                        ]);
                        
                        // Handle pagination if needed
                        $totalLocations = count($allLocations);
                        $allLocationsList = $allLocations;
                        $skip = 100;
                        
                        while ($totalLocations >= 100) {
                            Log::info('📄 Fetching additional page of all locations', [
                                'skip' => $skip,
                                'current_count' => $totalLocations
                            ]);
                            
                            $nextPageResponse = Http::timeout(30)
                                ->acceptJson()
                                ->withToken($accessToken)
                                ->withHeaders(['Version' => '2021-07-28'])
                                ->get($allLocationsUrl, [
                                    'companyId' => $companyId,
                                    'skip' => $skip,
                                    'limit' => 100
                                ]);
                            
                            if ($nextPageResponse->successful()) {
                                $nextPageData = $nextPageResponse->json();
                                $nextPageLocations = $nextPageData['locations'] ?? $nextPageData['data'] ?? [];
                                
                                if (is_array($nextPageLocations) && count($nextPageLocations) > 0) {
                                    $allLocationsList = array_merge($allLocationsList, $nextPageLocations);
                                    $totalLocations = count($nextPageLocations);
                                    $skip += 100;
                                    
                                    // Update location IDs list
                                    foreach ($nextPageLocations as $loc) {
                                        $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                                        if ($locId && !in_array($locId, $locationIds)) {
                                            $locationIds[] = $locId;
                                        }
                                    }
                                    
                                    // Re-check if selected location is found
                                    if ($selectedLocationId && !$selectedLocationFound && in_array($selectedLocationId, $locationIds)) {
                                        $selectedLocationFound = true;
                                    }
                                } else {
                                    break;
                                }
                            } else {
                                break;
                            }
                        }
                        
                        Log::info('📊 All Locations by Company ID - Final Summary', [
                            'companyId' => $companyId,
                            'total_locations_fetched' => count($allLocationsList),
                            'all_location_ids' => $locationIds,
                            'selectedLocationId' => $selectedLocationId ?? null,
                            'selectedLocationFound' => $selectedLocationFound,
                            'selectedLocationInList' => $selectedLocationId ? in_array($selectedLocationId, $locationIds) : false
                        ]);
                    } else {
                        Log::warning('⚠️ Failed to fetch all locations by company ID', [
                            'companyId' => $companyId,
                            'status' => $allLocationsResponse->status(),
                            'response' => $allLocationsResponse->json()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('❌ Exception fetching all locations by company ID', [
                        'companyId' => $companyId,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // For Company-level bulk installations, fetch installed locations and register provider for each
            // According to GHL docs, provider config must be created for the integration to appear
            // API Reference: https://marketplace.gohighlevel.com/docs/ghl/oauth/get-installed-locations
            if ($userType === 'Company' && $isBulk) {
                Log::info('🔄 Handling Company-level bulk installation - fetching installed locations', [
                    'companyId' => $locationId,
                    'selectedLocationId' => $selectedLocationId ?? null,
                    'userType' => $userType,
                    'isBulk' => $isBulk
                ]);
                
                // Use the installedLocations API to fetch locations where app is installed
                // API Reference: https://marketplace.gohighlevel.com/docs/ghl/oauth/get-installed-locations
                // This is the correct API for bulk installations - it returns only locations where app is installed
                try {
                    // Extract appId from client_id or token
                    // client_id format: "68323dc0642d285465c0b85a-mdxt9tp5"
                    // appId is the base part: "68323dc0642d285465c0b85a"
                    $clientId = config('services.external_auth.client_id', '68323dc0642d285465c0b85a-mdxt9tp5');
                    $appId = explode('-', $clientId)[0] ?? '68323dc0642d285465c0b85a';
                    
                    // Also try to get from token metadata if available
                    if ($tokenData && isset($tokenData['oauthMeta']['client'])) {
                        $appIdFromToken = $tokenData['oauthMeta']['client'] ?? null;
                        if ($appIdFromToken) {
                            $appId = $appIdFromToken;
                        }
                    }
                    
                    $installedLocationsUrl = "https://services.leadconnectorhq.com/oauth/installedLocations";
                    
                    // ===== STEP 1: Call installedLocations API BEFORE processing (to get baseline) =====
                    Log::info('🔍 [BEFORE] Fetching installed locations BEFORE processing', [
                        'companyId' => $locationId,
                        'appId' => $appId,
                        'clientId' => $clientId,
                        'url' => $installedLocationsUrl,
                        'api_docs' => 'https://marketplace.gohighlevel.com/docs/ghl/oauth/get-installed-locations'
                    ]);
                    
                    $locationsResponseBefore = Http::timeout(30)
                        ->acceptJson()
                        ->withToken($accessToken)
                        ->withHeaders(['Version' => '2021-07-28'])
                        ->get($installedLocationsUrl, [
                            'companyId' => $locationId,  // REQUIRED
                            'appId' => $appId,           // REQUIRED
                            'limit' => 100               // Get up to 100 locations per page
                            // Note: Not filtering by isInstalled to get all locations
                        ]);
                    
                    // Extract location IDs from BEFORE state
                    $locationIdsBefore = [];
                    $beforeLocations = [];
                    if ($locationsResponseBefore->successful()) {
                        $beforeData = $locationsResponseBefore->json();
                        $beforeLocations = $beforeData['locations'] ?? $beforeData['data'] ?? [];
                        
                        if (!is_array($beforeLocations)) {
                            $beforeLocations = [];
                        }
                        
                        foreach ($beforeLocations as $loc) {
                            $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                            if ($locId) {
                                $locationIdsBefore[] = $locId;
                            }
                        }
                        
                        Log::info('📋 [BEFORE] Installed locations BEFORE processing', [
                            'companyId' => $locationId,
                            'appId' => $appId,
                            'locations_count' => count($beforeLocations),
                            'location_ids' => $locationIdsBefore,
                            'locations' => array_map(function($loc) {
                                return [
                                    'id' => $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null, 
                                    'name' => $loc['name'] ?? $loc['locationName'] ?? null,
                                    'isInstalled' => $loc['isInstalled'] ?? false
                                ];
                            }, $beforeLocations)
                        ]);
                    } else {
                        Log::warning('⚠️ [BEFORE] Failed to fetch installed locations BEFORE processing', [
                            'status' => $locationsResponseBefore->status(),
                            'response' => $locationsResponseBefore->json()
                        ]);
                    }
                    
                    // ===== STEP 2: Now fetch installed locations for processing =====
                    Log::info('🔍 Fetching installed locations using installedLocations API', [
                        'companyId' => $locationId,
                        'appId' => $appId,
                        'clientId' => $clientId,
                        'url' => $installedLocationsUrl,
                        'api_docs' => 'https://marketplace.gohighlevel.com/docs/ghl/oauth/get-installed-locations'
                    ]);
                    
                    $locationsResponse = Http::timeout(30)
                        ->acceptJson()
                        ->withToken($accessToken)
                        ->withHeaders(['Version' => '2021-07-28'])
                        ->get($installedLocationsUrl, [
                            'companyId' => $locationId,  // REQUIRED
                            'appId' => $appId,           // REQUIRED
                            'isInstalled' => true,       // Filter only installed locations
                            'limit' => 100               // Get up to 100 locations per page
                        ]);
                    
                    Log::info('📡 Installed Locations API response', [
                        'status' => $locationsResponse->status(),
                        'successful' => $locationsResponse->successful(),
                        'response_keys' => $locationsResponse->successful() ? array_keys($locationsResponse->json() ?? []) : null,
                        'body_preview' => substr($locationsResponse->body(), 0, 500)
                    ]);
                    
                    if ($locationsResponse->successful()) {
                        $locationsData = $locationsResponse->json();
                        // Handle different response formats
                        $locations = $locationsData['locations'] ?? $locationsData['data'] ?? $locationsData ?? [];
                        
                        // Ensure it's an array
                        if (!is_array($locations)) {
                            $locations = [];
                        }
                        
                        Log::info('📋 Fetched installed locations for company', [
                            'companyId' => $locationId,
                            'appId' => $appId,
                            'locations_count' => count($locations),
                            'locations' => array_map(function($loc) {
                                return [
                                    'id' => $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null, 
                                    'name' => $loc['name'] ?? $loc['locationName'] ?? null,
                                    'isInstalled' => $loc['isInstalled'] ?? false
                                ];
                            }, $locations)
                        ]);
                        
                        // Use the selectedLocationId we extracted earlier (from referer/URL before OAuth)
                        // This represents the specific location the user clicked on for installation
                        
                        // Handle pagination if there are more locations
                        // Check if we need to fetch more pages
                        $totalLocations = count($locations);
                        $allLocations = $locations;
                        $skip = 100;
                        
                        // Fetch additional pages if limit was reached
                        while ($totalLocations >= 100) {
                            Log::info('📄 Fetching additional page of installed locations', [
                                'skip' => $skip,
                                'current_count' => $totalLocations
                            ]);
                            
                            $nextPageResponse = Http::timeout(30)
                                ->acceptJson()
                                ->withToken($accessToken)
                                ->withHeaders(['Version' => '2021-07-28'])
                                ->get($installedLocationsUrl, [
                                    'companyId' => $locationId,
                                    'appId' => $appId,
                                    'isInstalled' => true,
                                    'skip' => $skip,
                                    'limit' => 100
                                ]);
                            
                            if ($nextPageResponse->successful()) {
                                $nextPageData = $nextPageResponse->json();
                                $nextPageLocations = $nextPageData['locations'] ?? $nextPageData['data'] ?? [];
                                
                                if (is_array($nextPageLocations) && count($nextPageLocations) > 0) {
                                    $allLocations = array_merge($allLocations, $nextPageLocations);
                                    $totalLocations = count($nextPageLocations);
                                    $skip += 100;
                                } else {
                                    break; // No more locations
                                }
                            } else {
                                break; // Error fetching next page
                            }
                        }
                        
                        $locations = $allLocations;
                        Log::info('📊 Total installed locations fetched', [
                            'companyId' => $locationId,
                            'total_locations' => count($locations)
                        ]);
                        
                        // Register provider for each location
                        $successCount = 0;
                        $failCount = 0;
                        
                        // For bulk installations, we need to register provider for:
                        // 1. The selected location (if user clicked a direct link) - ALWAYS register this first
                        // 2. All locations where app is installed (isInstalled: true)
                        // 3. If no specific location selected and all are false, register for all locations
                        
                        $locationsToRegister = [];
                        $selectedLocationFound = false;
                        
                        // If a specific location was selected, prioritize it and ALWAYS register it
                        if ($selectedLocationId) {
                            $selectedLocation = null;
                            foreach ($locations as $loc) {
                                $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                                if ($locId === $selectedLocationId) {
                                    $selectedLocation = $loc;
                                    $selectedLocationFound = true;
                                    break;
                                }
                            }
                            
                            if ($selectedLocation) {
                                $locationsToRegister[] = $selectedLocation;
                                Log::info('✅ Found selected location in API response - will register provider', [
                                    'locationId' => $selectedLocationId,
                                    'locationName' => $selectedLocation['name'] ?? 'N/A',
                                    'isInstalled' => $selectedLocation['isInstalled'] ?? false
                                ]);
                            } else {
                                // Location not in API response, but we MUST register it anyway
                                // This is the location the user specifically selected
                                $locationsToRegister[] = [
                                    '_id' => $selectedLocationId,
                                    'id' => $selectedLocationId,
                                    'name' => 'Selected Location (Not in API response)',
                                    'isInstalled' => false
                                ];
                                Log::info('⚠️ Selected location NOT in API response - will register provider anyway', [
                                    'locationId' => $selectedLocationId,
                                    'note' => 'This is the location the user clicked on, so we must register the provider for it'
                                ]);
                            }
                        }
                        
                        // Also add all locations where app is installed
                        foreach ($locations as $location) {
                            $locId = $location['_id'] ?? $location['id'] ?? $location['locationId'] ?? null;
                            $isInstalled = $location['isInstalled'] ?? false;
                            
                            // Skip if already added (selected location)
                            if ($selectedLocationId && $locId === $selectedLocationId) {
                                continue;
                            }
                            
                            // Add if installed
                            if ($isInstalled) {
                                $locationsToRegister[] = $location;
                            }
                        }
                        
                        // If no locations to register yet, and no specific selection, register for all
                        // This handles the case where bulk installation is new and locations aren't marked as installed yet
                        if (empty($locationsToRegister) && !$selectedLocationId) {
                            Log::info('ℹ️ No installed locations found and no specific selection - registering for all locations', [
                                'total_locations' => count($locations)
                            ]);
                            $locationsToRegister = $locations;
                        }
                        
                        Log::info('📝 Locations to register provider for', [
                            'count' => count($locationsToRegister),
                            'selectedLocationId' => $selectedLocationId,
                            'locations' => array_map(function($loc) {
                                return [
                                    'id' => $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null,
                                    'name' => $loc['name'] ?? 'N/A',
                                    'isInstalled' => $loc['isInstalled'] ?? false
                                ];
                            }, $locationsToRegister)
                        ]);
                        
                        // Register provider for each location
                        $successCount = 0;
                        $failCount = 0;
                        
                        foreach ($locationsToRegister as $location) {
                            // Handle different response formats - API returns _id
                            $actualLocationId = $location['_id'] ?? $location['id'] ?? $location['locationId'] ?? null;
                            if (!$actualLocationId) {
                                Log::warning('⚠️ Location entry missing ID', [
                                    'location_data' => $location
                                ]);
                                continue;
                            }
                            
                            $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
                                        . '?locationId=' . urlencode($actualLocationId);
                            
                            $providerPayload = [
                                'name'        => 'Tap Payments',
                                'description' => 'Innovating payment acceptance & collection in MENA',
                                'paymentsUrl' => 'https://dashboard.mediasolution.io/tap',
                                'queryUrl'    => 'https://dashboard.mediasolution.io/api/payment/query',
                                'imageUrl'    => 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg',
                            ];
                            
                            try {
                                Log::info('📤 [PROVIDER REGISTRATION] Request details', [
                                    'locationId' => $actualLocationId,
                                    'locationName' => $location['name'] ?? 'N/A',
                                    'url' => $providerUrl,
                                    'payload' => $providerPayload,
                                    'has_access_token' => !empty($accessToken),
                                    'token_preview' => $accessToken ? substr($accessToken, 0, 20) . '...' : null
                                ]);
                                
                                $startTime = microtime(true);
                                $providerResp = Http::timeout(30)
                                    ->acceptJson()
                                    ->withToken($accessToken)
                                    ->withHeaders(['Version' => '2021-07-28'])
                                    ->post($providerUrl, $providerPayload);
                                $endTime = microtime(true);
                                $duration = round(($endTime - $startTime) * 1000, 2);
                                
                                Log::info('📥 [PROVIDER REGISTRATION] Response details', [
                                    'locationId' => $actualLocationId,
                                    'locationName' => $location['name'] ?? 'N/A',
                                    'status_code' => $providerResp->status(),
                                    'successful' => $providerResp->successful(),
                                    'failed' => $providerResp->failed(),
                                    'client_error' => $providerResp->clientError(),
                                    'server_error' => $providerResp->serverError(),
                                    'duration_ms' => $duration,
                                    'response_headers' => $providerResp->headers(),
                                    'response_body_raw' => $providerResp->body(),
                                    'response_body_json' => $providerResp->json(),
                                    'response_body_string' => (string) $providerResp->body(),
                                    'response_size' => strlen($providerResp->body())
                                ]);
                                
                                if ($providerResp->successful()) {
                                    $successCount++;
                                    $responseData = $providerResp->json();
                                    Log::info('✅ Provider registered for location', [
                                        'locationId' => $actualLocationId,
                                        'locationName' => $location['name'] ?? 'N/A',
                                        'status_code' => $providerResp->status(),
                                        'response_data' => $responseData,
                                        'provider_id' => $responseData['id'] ?? $responseData['providerId'] ?? $responseData['_id'] ?? null,
                                        'provider_name' => $responseData['name'] ?? null,
                                        'duration_ms' => $duration
                                    ]);
                                } else {
                                    $failCount++;
                                    $responseData = $providerResp->json();
                                    $errorMessage = $responseData['message'] ?? $responseData['error'] ?? $responseData['errorMessage'] ?? 'Unknown error';
                                    Log::error('❌ [PROVIDER REGISTRATION] Failed to register provider for location', [
                                        'locationId' => $actualLocationId,
                                        'locationName' => $location['name'] ?? 'N/A',
                                        'status_code' => $providerResp->status(),
                                        'status_text' => $providerResp->reason(),
                                        'error_message' => $errorMessage,
                                        'response_body' => $providerResp->body(),
                                        'response_json' => $responseData,
                                        'response_headers' => $providerResp->headers(),
                                        'duration_ms' => $duration,
                                        'possible_causes' => [
                                            'missing_scope' => !in_array('payments/custom-provider.write', $tokenData['oauthMeta']['scopes'] ?? []),
                                            'locationId_mismatch' => ($tokenData['primaryAuthClassId'] ?? $tokenData['authClassId'] ?? null) !== $actualLocationId,
                                            'invalid_location_id' => empty($actualLocationId),
                                            'token_expired' => false, // Would need to check token expiry
                                            'api_error' => $providerResp->serverError()
                                        ]
                                    ]);
                                }
                            } catch (\Exception $e) {
                                $failCount++;
                                Log::error('❌ [PROVIDER REGISTRATION] Exception registering provider for location', [
                                    'locationId' => $actualLocationId,
                                    'locationName' => $location['name'] ?? 'N/A',
                                    'error_message' => $e->getMessage(),
                                    'error_code' => $e->getCode(),
                                    'error_file' => $e->getFile(),
                                    'error_line' => $e->getLine(),
                                    'error_trace' => $e->getTraceAsString(),
                                    'url' => $providerUrl,
                                    'payload' => $providerPayload
                                ]);
                            }
                        }
                        
                        Log::info('📊 Bulk provider registration summary', [
                            'companyId' => $locationId,
                            'total_locations' => count($locations),
                            'successful' => $successCount,
                            'failed' => $failCount
                        ]);
                        
                        // ===== STEP 3: Call installedLocations API AFTER processing (to find newly selected location) =====
                        Log::info('🔍 [AFTER] Fetching installed locations AFTER processing', [
                            'companyId' => $locationId,
                            'appId' => $appId,
                            'url' => $installedLocationsUrl
                        ]);
                        
                        // Wait a moment for GHL to update the installation status
                        sleep(1);
                        
                        $locationsResponseAfter = Http::timeout(30)
                            ->acceptJson()
                            ->withToken($accessToken)
                            ->withHeaders(['Version' => '2021-07-28'])
                            ->get($installedLocationsUrl, [
                                'companyId' => $locationId,
                                'appId' => $appId,
                                'limit' => 100
                                // Note: Not filtering by isInstalled to get all locations
                            ]);
                        
                        // Extract location IDs from AFTER state
                        $locationIdsAfter = [];
                        $afterLocations = [];
                        if ($locationsResponseAfter->successful()) {
                            $afterData = $locationsResponseAfter->json();
                            $afterLocations = $afterData['locations'] ?? $afterData['data'] ?? [];
                            
                            if (!is_array($afterLocations)) {
                                $afterLocations = [];
                            }
                            
                            foreach ($afterLocations as $loc) {
                                $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                                if ($locId) {
                                    $locationIdsAfter[] = $locId;
                                }
                            }
                            
                            Log::info('📋 [AFTER] Installed locations AFTER processing', [
                                'companyId' => $locationId,
                                'appId' => $appId,
                                'locations_count' => count($afterLocations),
                                'location_ids' => $locationIdsAfter,
                                'locations' => array_map(function($loc) {
                                    return [
                                        'id' => $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null, 
                                        'name' => $loc['name'] ?? $loc['locationName'] ?? null,
                                        'isInstalled' => $loc['isInstalled'] ?? false
                                    ];
                                }, $afterLocations)
                            ]);
                        } else {
                            Log::warning('⚠️ [AFTER] Failed to fetch installed locations AFTER processing', [
                                'status' => $locationsResponseAfter->status(),
                                'response' => $locationsResponseAfter->json()
                            ]);
                        }
                        
                        // ===== STEP 4: Compare BEFORE and AFTER to find newly selected location =====
                        $newlySelectedLocationId = null;
                        
                        // Build maps of location ID => isInstalled status for both BEFORE and AFTER
                        $beforeStatusMap = [];
                        if (!empty($beforeLocations)) {
                            foreach ($beforeLocations as $loc) {
                                $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                                if ($locId) {
                                    $beforeStatusMap[$locId] = $loc['isInstalled'] ?? false;
                                }
                            }
                        }
                        
                        $afterStatusMap = [];
                        if (!empty($afterLocations)) {
                            foreach ($afterLocations as $loc) {
                                $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                                if ($locId) {
                                    $afterStatusMap[$locId] = $loc['isInstalled'] ?? false;
                                }
                            }
                        }
                        
                        Log::info('🔍 [COMPARISON] Building status maps for comparison', [
                            'before_status_map_count' => count($beforeStatusMap),
                            'after_status_map_count' => count($afterStatusMap),
                            'before_installed_count' => count(array_filter($beforeStatusMap)),
                            'after_installed_count' => count(array_filter($afterStatusMap))
                        ]);
                        
                        // Find locations where isInstalled changed from false to true
                        $statusChangedLocations = [];
                        foreach ($afterStatusMap as $locId => $isInstalledAfter) {
                            $isInstalledBefore = $beforeStatusMap[$locId] ?? false;
                            
                            // Location changed from not installed to installed
                            if (!$isInstalledBefore && $isInstalledAfter) {
                                $statusChangedLocations[] = $locId;
                            }
                        }
                        
                        // Also find locations that are newly added (in AFTER but not in BEFORE)
                        $newLocationIds = array_diff($locationIdsAfter, $locationIdsBefore);
                        
                        // Combine both: status changes and new locations
                        $candidateLocationIds = array_unique(array_merge($statusChangedLocations, $newLocationIds));
                        
                        if (!empty($candidateLocationIds)) {
                            // If multiple candidates, prioritize the one that matches selectedLocationId if we have it
                            if ($selectedLocationId && in_array($selectedLocationId, $candidateLocationIds)) {
                                $newlySelectedLocationId = $selectedLocationId;
                            } else {
                                // Prefer status changes over new locations
                                if (!empty($statusChangedLocations)) {
                                    $newlySelectedLocationId = reset($statusChangedLocations);
                                } else {
                                    $newlySelectedLocationId = reset($newLocationIds);
                                }
                            }
                            
                            Log::info('✅ [COMPARISON] Found newly selected location by comparing BEFORE and AFTER', [
                                'newlySelectedLocationId' => $newlySelectedLocationId,
                                'status_changed_locations' => $statusChangedLocations,
                                'new_location_ids' => array_values($newLocationIds),
                                'all_candidate_ids' => array_values($candidateLocationIds),
                                'selectedLocationId_from_extraction' => $selectedLocationId ?? null
                            ]);
                            
                            // Always update selectedLocationId with the newly found location (this is the most accurate)
                            $oldSelectedLocationId = $selectedLocationId;
                            $selectedLocationId = $newlySelectedLocationId;
                            $extractionSource = 'before_after_comparison';
                            Log::info('✅ [EXTRACTION] Updated selectedLocationId from BEFORE/AFTER comparison', [
                                'old_selectedLocationId' => $oldSelectedLocationId,
                                'new_selectedLocationId' => $selectedLocationId,
                                'source' => $extractionSource
                            ]);
                        } else {
                            // No status changes or new locations found
                            // Check if there are locations with isInstalled: true in AFTER that we registered providers for
                            // These might be the selected location even if status didn't change
                            $installedInAfter = [];
                            foreach ($afterStatusMap as $locId => $isInstalled) {
                                if ($isInstalled) {
                                    $installedInAfter[] = $locId;
                                }
                            }
                            
                            // Check which of these installed locations we registered providers for
                            $registeredLocationIds = [];
                            foreach ($locationsToRegister as $regLoc) {
                                $regLocId = $regLoc['_id'] ?? $regLoc['id'] ?? $regLoc['locationId'] ?? null;
                                if ($regLocId) {
                                    $registeredLocationIds[] = $regLocId;
                                }
                            }
                            
                            // Find installed locations that we registered providers for
                            $installedAndRegistered = array_intersect($installedInAfter, $registeredLocationIds);
                            
                            if (!empty($installedAndRegistered)) {
                                // If we have a selectedLocationId hint, use it if it's in the list
                                if ($selectedLocationId && in_array($selectedLocationId, $installedAndRegistered)) {
                                    $newlySelectedLocationId = $selectedLocationId;
                                } else {
                                    // Use the first installed and registered location
                                    $newlySelectedLocationId = reset($installedAndRegistered);
                                }
                                
                                Log::info('✅ [COMPARISON] Found selected location from installed and registered locations', [
                                    'newlySelectedLocationId' => $newlySelectedLocationId,
                                    'installed_and_registered' => array_values($installedAndRegistered),
                                    'selectedLocationId_from_extraction' => $selectedLocationId ?? null
                                ]);
                                
                                $oldSelectedLocationId = $selectedLocationId;
                                $selectedLocationId = $newlySelectedLocationId;
                                $extractionSource = 'installed_and_registered';
                                Log::info('✅ [EXTRACTION] Updated selectedLocationId from installed and registered locations', [
                                    'old_selectedLocationId' => $oldSelectedLocationId,
                                    'new_selectedLocationId' => $selectedLocationId,
                                    'source' => $extractionSource
                                ]);
                            } else {
                                // No status changes or new locations found
                                // Check locations with isInstalled: true in AFTER that are NOT in the main processing list
                                // These are locations that were already installed but are the ones selected during this flow
                                $mainProcessingLocationIds = [];
                                foreach ($locations as $loc) {
                                    $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                                    if ($locId) {
                                        $mainProcessingLocationIds[] = $locId;
                                    }
                                }
                                
                                // Find installed locations in AFTER that are NOT in main processing list
                                $installedButNotInMainProcessing = [];
                                foreach ($afterStatusMap as $locId => $isInstalled) {
                                    if ($isInstalled && !in_array($locId, $mainProcessingLocationIds)) {
                                        $installedButNotInMainProcessing[] = $locId;
                                    }
                                }
                                
                                if (!empty($installedButNotInMainProcessing)) {
                                    // These are locations that were already installed (so not in main processing)
                                    // but are the ones selected during this installation flow
                                    if ($selectedLocationId && in_array($selectedLocationId, $installedButNotInMainProcessing)) {
                                        $newlySelectedLocationId = $selectedLocationId;
                                    } else {
                                        $newlySelectedLocationId = reset($installedButNotInMainProcessing);
                                    }
                                    
                                    Log::info('✅ [COMPARISON] Found selected location from installed locations not in main processing', [
                                        'newlySelectedLocationId' => $newlySelectedLocationId,
                                        'installed_but_not_in_main_processing' => $installedButNotInMainProcessing,
                                        'main_processing_location_ids' => $mainProcessingLocationIds,
                                        'selectedLocationId_from_extraction' => $selectedLocationId ?? null
                                    ]);
                                    
                                    $oldSelectedLocationId = $selectedLocationId;
                                    $selectedLocationId = $newlySelectedLocationId;
                                    $extractionSource = 'installed_not_in_main_processing';
                                    Log::info('✅ [EXTRACTION] Updated selectedLocationId from installed locations not in main processing', [
                                        'old_selectedLocationId' => $oldSelectedLocationId,
                                        'new_selectedLocationId' => $selectedLocationId,
                                        'source' => $extractionSource
                                    ]);
                                } else {
                                    Log::info('ℹ️ [COMPARISON] No status changes, new locations, or installed locations not in main processing', [
                                        'location_ids_before' => $locationIdsBefore,
                                        'location_ids_after' => $locationIdsAfter,
                                        'status_changed_locations' => $statusChangedLocations,
                                        'new_location_ids' => array_values($newLocationIds),
                                        'installed_in_after' => $installedInAfter,
                                        'registered_location_ids' => $registeredLocationIds,
                                        'main_processing_location_ids' => $mainProcessingLocationIds,
                                        'installed_but_not_in_main_processing' => $installedButNotInMainProcessing,
                                        'selectedLocationId_from_extraction' => $selectedLocationId ?? null
                                    ]);
                                }
                            }
                        }
                        
                        // ===== STEP 5: If we found a newly selected location, update user and ensure it's registered =====
                        if ($newlySelectedLocationId) {
                            // Update user record with the newly found location ID
                            // Also update userType to "Location" (same as marketplace installation flow)
                            if ($user && $user->exists) {
                                $oldLocationId = $user->lead_location_id;
                                $oldUserType = $user->lead_user_type;
                                $user->lead_location_id = $newlySelectedLocationId;
                                // When a specific location is selected, userType should be "Location" (not "Company")
                                // This matches the marketplace installation flow behavior
                                $user->lead_user_type = 'Location';
                                try {
                                    $user->save();
                                    Log::info('✅ Updated user record with newly selected location ID and userType', [
                                        'user_id' => $user->id,
                                        'old_location_id' => $oldLocationId,
                                        'new_location_id' => $newlySelectedLocationId,
                                        'old_user_type' => $oldUserType,
                                        'new_user_type' => 'Location',
                                        'note' => 'Changed userType to Location (same as marketplace installation flow)'
                                    ]);
                                } catch (\Exception $e) {
                                    Log::error('❌ Failed to update user record with newly selected location ID', [
                                        'user_id' => $user->id,
                                        'new_location_id' => $newlySelectedLocationId,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                            
                            // Find the location data from the AFTER response
                            $newlySelectedLocation = null;
                            foreach ($afterLocations as $loc) {
                                $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                                if ($locId === $newlySelectedLocationId) {
                                    $newlySelectedLocation = $loc;
                                    break;
                                }
                            }
                            
                            // Check if provider was already registered for this location
                            $alreadyRegistered = false;
                            foreach ($locationsToRegister as $regLoc) {
                                $regLocId = $regLoc['_id'] ?? $regLoc['id'] ?? $regLoc['locationId'] ?? null;
                                if ($regLocId === $newlySelectedLocationId) {
                                    $alreadyRegistered = true;
                                    break;
                                }
                            }
                            
                            if ($newlySelectedLocation && !$alreadyRegistered) {
                                // Register provider for the newly selected location
                                $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
                                            . '?locationId=' . urlencode($newlySelectedLocationId);
                                
                                $providerPayload = [
                                    'name'        => 'Tap Payments',
                                    'description' => 'Innovating payment acceptance & collection in MENA',
                                    'paymentsUrl' => 'https://dashboard.mediasolution.io/tap',
                                    'queryUrl'    => 'https://dashboard.mediasolution.io/api/payment/query',
                                    'imageUrl'    => 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg',
                                ];
                                
                                try {
                                    $providerResp = Http::timeout(30)
                                        ->acceptJson()
                                        ->withToken($accessToken)
                                        ->withHeaders(['Version' => '2021-07-28'])
                                        ->post($providerUrl, $providerPayload);
                                    
                                    if ($providerResp->successful()) {
                                        Log::info('✅ Provider registered for newly selected location (from BEFORE/AFTER comparison)', [
                                            'locationId' => $newlySelectedLocationId,
                                            'locationName' => $newlySelectedLocation['name'] ?? 'N/A'
                                        ]);
                                    } else {
                                        Log::warning('⚠️ Failed to register provider for newly selected location', [
                                            'locationId' => $newlySelectedLocationId,
                                            'status' => $providerResp->status(),
                                            'response' => $providerResp->json()
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    Log::error('❌ Exception registering provider for newly selected location', [
                                        'locationId' => $newlySelectedLocationId,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            } elseif ($alreadyRegistered) {
                                Log::info('ℹ️ Provider already registered for newly selected location', [
                                    'locationId' => $newlySelectedLocationId
                                ]);
                            }
                        }
                    } else {
                        Log::warning('⚠️ Could not fetch installed locations using installedLocations API', [
                            'companyId' => $locationId,
                            'appId' => $appId,
                            'status' => $locationsResponse->status(),
                            'response' => $locationsResponse->json(),
                            'api_endpoint' => $installedLocationsUrl,
                            'note' => 'Falling back to direct registration with company ID'
                        ]);
                        
                        // Fallback: Try to register with company ID directly
                        // This might work for some GHL configurations
            $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
                        . '?locationId=' . urlencode($locationId);
                
              $providerPayload = [
            'name'        => 'Tap Payments',
            'description' => 'Innovating payment acceptance & collection in MENA',
            'paymentsUrl' => 'https://dashboard.mediasolution.io/tap',
            'queryUrl'    => 'https://dashboard.mediasolution.io/api/payment/query',
            'imageUrl'    => 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg',
            ];

                        try {
                            $providerResp = Http::timeout(30)
                                ->acceptJson()
                                ->withToken($accessToken)
                                ->withHeaders(['Version' => '2021-07-28'])
                                ->post($providerUrl, $providerPayload);
                            
                            if ($providerResp->successful()) {
                                Log::info('✅ Provider registered with company ID (fallback)', [
                                    'companyId' => $locationId
                                ]);
                            } else {
                                Log::warning('⚠️ Fallback registration failed', [
                                    'companyId' => $locationId,
                                    'status' => $providerResp->status(),
                                    'response' => $providerResp->json()
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('❌ Exception in fallback provider registration', [
                                'companyId' => $locationId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('❌ Exception fetching installed locations for bulk installation', [
                        'companyId' => $locationId,
                        'appId' => $appId ?? 'unknown',
                        'api_endpoint' => $installedLocationsUrl ?? 'N/A',
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_file' => $e->getFile(),
                        'error_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            } else {
                // Only register provider for Location-level installations
                $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
                            . '?locationId=' . urlencode($locationId);
                
                $providerPayload = [
                    'name'        => 'Tap Payments',
                    'description' => 'Innovating payment acceptance & collection in MENA',
                    'paymentsUrl' => 'https://dashboard.mediasolution.io/tap',
                    'queryUrl'    => 'https://dashboard.mediasolution.io/api/payment/query',
                    'imageUrl'    => 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg',
                ];

                // Log request details before making the call
                Log::info('=== PROVIDER REGISTRATION REQUEST ===', [
                'url' => $providerUrl,
                'locationId' => $locationId,
                'payload' => $providerPayload,
                'has_access_token' => !empty($accessToken),
                'access_token_preview' => $accessToken ? substr($accessToken, 0, 20) . '...' : null,
                'token_scopes_from_payload' => $tokenData['oauthMeta']['scopes'] ?? null,
                'token_locationId' => $tokenData['primaryAuthClassId'] ?? $tokenData['authClassId'] ?? null,
                'userType' => $userType,
                'isBulk' => $isBulk,
                    'headers' => [
                        'Version' => '2021-07-28',
                        'Authorization' => 'Bearer ' . ($accessToken ? substr($accessToken, 0, 20) . '...' : 'MISSING')
                    ],
                    'timeout' => 40
                ]);

                try {
                    $startTime = microtime(true);

            $providerResp = Http::timeout(40)
                ->acceptJson()
                ->withToken($accessToken)
                ->withHeaders(['Version' => '2021-07-28'])
                ->post($providerUrl, $providerPayload); 
                    
                    $endTime = microtime(true);
                    $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds

                    // Log full response details
                    Log::info('=== PROVIDER REGISTRATION RESPONSE ===', [
                    'locationId' => $locationId,
                    'status_code' => $providerResp->status(),
                    'successful' => $providerResp->successful(),
                    'failed' => $providerResp->failed(),
                    'client_error' => $providerResp->clientError(),
                    'server_error' => $providerResp->serverError(),
                    'duration_ms' => $duration,
                    'response_headers' => $providerResp->headers(),
                    'response_body_raw' => $providerResp->body(),
                    'response_body_json' => $providerResp->json(),
                        'response_body_string' => (string) $providerResp->body()
                    ]);

            if ($providerResp->failed()) {
                        $responseJson = $providerResp->json();
                        $errorMessage = $responseJson['message'] ?? $responseJson['error'] ?? 'Unknown error';
                        
                        Log::error('❌ PROVIDER ASSOCIATION FAILED - Integration may not be visible', [
                    'locationId' => $locationId,
                        'status_code' => $providerResp->status(),
                        'status_text' => $providerResp->reason(),
                        'error_message' => $errorMessage,
                        'response_body' => $providerResp->body(),
                        'response_json' => $responseJson,
                        'response_headers' => $providerResp->headers(),
                        'userType' => $userType,
                        'isBulk' => $isBulk,
                        'token_scopes' => $tokenData ? ($tokenData['oauthMeta']['scopes'] ?? null) : null,
                        'token_has_custom_provider_write' => $tokenData && !empty($tokenData['oauthMeta']['scopes']) && in_array('payments/custom-provider.write', $tokenData['oauthMeta']['scopes'] ?? []),
                        'error_details' => [
                            'is_4xx' => $providerResp->clientError(),
                            'is_5xx' => $providerResp->serverError(),
                            'has_body' => !empty($providerResp->body()),
                            'body_length' => strlen($providerResp->body()),
                            'is_403_forbidden' => $providerResp->status() === 403,
                            'possible_causes' => [
                                'missing_scope' => $tokenData ? !in_array('payments/custom-provider.write', $tokenData['oauthMeta']['scopes'] ?? []) : 'token_not_decoded',
                                'locationId_mismatch' => $tokenData ? (($tokenData['primaryAuthClassId'] ?? $tokenData['authClassId'] ?? null) !== $locationId) : 'token_not_decoded',
                                'bulk_installation' => $isBulk,
                                'company_level_token' => $userType === 'Company'
                            ]
                        ],
                        'suggestions' => [
                            'Check if token has payments/custom-provider.write scope',
                            'Verify locationId matches token authorized location',
                            'For bulk installations, provider registration might need to be done manually',
                                'Company-level tokens might require different authentication'
                            ]
                ]);
                // This is critical - if provider registration fails, the integration won't appear
                // We'll still save the user but log the error for debugging
            } else {
                        Log::info('✅ PROVIDER ASSOCIATION SUCCESSFUL', [
                    'locationId' => $locationId,
                            'status_code' => $providerResp->status(),
                            'response_body' => $providerResp->json(),
                            'duration_ms' => $duration
                ]);
            }
                } catch (\Exception $e) {
                    Log::error('❌ EXCEPTION during provider registration', [
                        'locationId' => $locationId,
                        'exception_message' => $e->getMessage(),
                        'exception_code' => $e->getCode(),
                        'exception_file' => $e->getFile(),
                        'exception_line' => $e->getLine(),
                        'exception_trace' => $e->getTraceAsString(),
                        'url' => $providerUrl ?? 'N/A',
                        'payload' => $providerPayload ?? 'N/A'
                    ]);
                    // Continue execution even if provider registration fails
                }
            } // End else block for non-bulk Company installations

            // For Company-level bulk installations, return success response (no redirect)
            if ($userType === 'Company' && $isBulk) {
                // Use selected location ID if available, otherwise use company ID
                $finalLocationId = $selectedLocationId ?? $userLocationId ?? $locationId;
                
                Log::info('✅ Bulk installation completed successfully (no redirect)', [
                    'companyId' => $locationId,
                    'selectedLocationId' => $selectedLocationId ?? null,
                    'userLocationId' => $userLocationId ?? null,
                    'finalLocationId' => $finalLocationId,
                    'user_id' => $user->id,
                    'user_location_id' => $user->lead_location_id,
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Integration installed successfully',
                    'companyId' => $locationId,
                    'locationId' => $finalLocationId, // Use selected location ID, not company ID
                    'userId' => $user->id
                ], 200);
            } else {
                // For Location-level installations, redirect to integrations page
                $redirectUrl = "https://app.gohighlevel.com/v2/location/{$locationId}/payments/integrations";
                Log::info('Redirecting to GHL location integrations page', [
                    'locationId' => $locationId,
                    'redirectUrl' => $redirectUrl,
                    'user_id' => $user->id,
                ]);
                
                return redirect($redirectUrl);
            }

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
                'error' => 'Invalid locationId'
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
         * Send webhook event to LeadConnector backend
         * This should be called BEFORE verifying with Tap API
         */
        private function sendWebhookToLeadConnector(Request $request, User $user): bool
        
        {
            try {
                $event = $request->input('event');
                $locationId = $request->input('locationId');
                $apiKey = $request->input('apiKey');
                
                Log::info('=== START: Sending webhook to LeadConnector ===', [
                    'event' => $event,
                    'locationId' => $locationId,
                    'user_id' => $user->id,
                    'tap_mode' => $user->tap_mode,
                    'timestamp' => now()->toIso8601String()
                ]);
                
                // Build payload based on event type
                Log::info('Building webhook payload', [
                    'event' => $event,
                    'locationId' => $locationId
                ]);
                
                $payload = $this->buildWebhookPayload($request, $event, $locationId, $apiKey);
                
                if (!$payload) {
                    Log::error('Failed to build webhook payload', [
                        'event' => $event,
                        'locationId' => $locationId,
                        'request_keys' => array_keys($request->all())
                    ]);
                    return false;
                }
                
                Log::info('Webhook payload built successfully', [
                    'event' => $event,
                    'locationId' => $locationId,
                    'payload_keys' => array_keys($payload),
                    'payload_size' => strlen(json_encode($payload)),
                    'has_chargeId' => isset($payload['chargeId']),
                    'has_ghlTransactionId' => isset($payload['ghlTransactionId']),
                    'has_ghlSubscriptionId' => isset($payload['ghlSubscriptionId']),
                    'has_chargeSnapshot' => isset($payload['chargeSnapshot']),
                    'has_subscriptionSnapshot' => isset($payload['subscriptionSnapshot']),
                    'has_marketplaceAppId' => isset($payload['marketplaceAppId'])
                ]);
                
                // Log full payload for debugging (be careful with sensitive data)
                Log::debug('Full webhook payload (sanitized)', [
                    'event' => $payload['event'],
                    'locationId' => $payload['locationId'],
                    'apiKey_prefix' => substr($payload['apiKey'] ?? '', 0, 10) . '...',
                    'chargeId' => $payload['chargeId'] ?? null,
                    'ghlTransactionId' => $payload['ghlTransactionId'] ?? null,
                    'ghlSubscriptionId' => $payload['ghlSubscriptionId'] ?? null,
                    'marketplaceAppId' => $payload['marketplaceAppId'] ?? null,
                    'chargeSnapshot_keys' => isset($payload['chargeSnapshot']) ? array_keys($payload['chargeSnapshot']) : null,
                    'subscriptionSnapshot_keys' => isset($payload['subscriptionSnapshot']) ? array_keys($payload['subscriptionSnapshot']) : null
                ]);
                
                // Send webhook to LeadConnector backend
                $webhookUrl = 'https://backend.leadconnectorhq.com/payments/custom-provider/webhook';
                
                Log::info('Sending HTTP POST request to LeadConnector', [
                    'url' => $webhookUrl,
                    'event' => $event,
                    'locationId' => $locationId,
                    'timeout' => 30
                ]);
                
                $startTime = microtime(true);
                $response = Http::timeout(30)
                    ->acceptJson()
                    ->post($webhookUrl, $payload);
                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds
                
                Log::info('LeadConnector webhook response received', [
                    'event' => $event,
                    'locationId' => $locationId,
                    'status_code' => $response->status(),
                    'successful' => $response->successful(),
                    'duration_ms' => $duration,
                    'response_headers' => $response->headers()
                ]);
                
                if ($response->successful()) {
                    $responseBody = $response->json();
                    Log::info('=== SUCCESS: Webhook sent to LeadConnector ===', [
                        'event' => $event,
                        'locationId' => $locationId,
                        'response_status' => $response->status(),
                        'response_body' => $responseBody,
                        'duration_ms' => $duration,
                        'timestamp' => now()->toIso8601String()
                    ]);
                    return true;
                } else {
                    $errorResponse = $response->json() ?? $response->body();
                    Log::error('=== FAILED: Webhook send to LeadConnector ===', [
                        'event' => $event,
                        'locationId' => $locationId,
                        'status_code' => $response->status(),
                        'response_body' => $errorResponse,
                        'response_raw' => $response->body(),
                        'duration_ms' => $duration,
                        'timestamp' => now()->toIso8601String()
                    ]);
                    return false;
                }
                
            } catch (\Exception $e) {
                Log::error('=== EXCEPTION: Error sending webhook to LeadConnector ===', [
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'event' => $request->input('event'),
                    'locationId' => $request->input('locationId'),
                    'trace' => $e->getTraceAsString(),
                    'timestamp' => now()->toIso8601String()
                ]);
                return false;
            }
        }
        
        /**
         * Build webhook payload based on event type
         */
        private function buildWebhookPayload(Request $request, string $event, string $locationId, string $apiKey): ?array
        {
            Log::debug('Building webhook payload', [
                'event' => $event,
                'locationId' => $locationId,
                'available_request_keys' => array_keys($request->all())
            ]);
            
            $payload = [
                'event' => $event,
                'locationId' => $locationId,
                'apiKey' => $apiKey,
            ];
            
            switch ($event) {
                case 'payment.captured':
                    // Required: event, chargeId, ghlTransactionId, chargeSnapshot, locationId, apiKey
                    $payload['chargeId'] = $request->input('chargeId');
                    $payload['ghlTransactionId'] = $request->input('ghlTransactionId');
                    $payload['chargeSnapshot'] = $request->input('chargeSnapshot', []);
                    
                    Log::debug('Built payment.captured payload', [
                        'chargeId' => $payload['chargeId'],
                        'ghlTransactionId' => $payload['ghlTransactionId'],
                        'chargeSnapshot_size' => is_array($payload['chargeSnapshot']) ? count($payload['chargeSnapshot']) : 0
                    ]);
                    break;
                    
                case 'subscription.updated':
                    // Required: event, ghlSubscriptionId, subscriptionSnapshot, locationId, apiKey
                    $payload['ghlSubscriptionId'] = $request->input('ghlSubscriptionId');
                    $payload['subscriptionSnapshot'] = $request->input('subscriptionSnapshot', []);
                    
                    Log::debug('Built subscription.updated payload', [
                        'ghlSubscriptionId' => $payload['ghlSubscriptionId'],
                        'subscriptionSnapshot_size' => is_array($payload['subscriptionSnapshot']) ? count($payload['subscriptionSnapshot']) : 0
                    ]);
                    break;
                    
                case 'subscription.trialing':
                case 'subscription.active':
                    // Required: event, chargeId, ghlTransactionId, ghlSubscriptionId, marketplaceAppId, locationId, apiKey
                    $payload['chargeId'] = $request->input('chargeId');
                    $payload['ghlTransactionId'] = $request->input('ghlTransactionId');
                    $payload['ghlSubscriptionId'] = $request->input('ghlSubscriptionId');
                    $payload['marketplaceAppId'] = $request->input('marketplaceAppId');
                    
                    Log::debug('Built ' . $event . ' payload', [
                        'chargeId' => $payload['chargeId'],
                        'ghlTransactionId' => $payload['ghlTransactionId'],
                        'ghlSubscriptionId' => $payload['ghlSubscriptionId'],
                        'marketplaceAppId' => $payload['marketplaceAppId']
                    ]);
                    break;
                    
                case 'subscription.charged':
                    // Required: event, chargeId, ghlSubscriptionId, subscriptionSnapshot, chargeSnapshot, locationId, apiKey
                    $payload['chargeId'] = $request->input('chargeId');
                    $payload['ghlSubscriptionId'] = $request->input('ghlSubscriptionId');
                    $payload['subscriptionSnapshot'] = $request->input('subscriptionSnapshot', []);
                    $payload['chargeSnapshot'] = $request->input('chargeSnapshot', []);
                    
                    Log::debug('Built subscription.charged payload', [
                        'chargeId' => $payload['chargeId'],
                        'ghlSubscriptionId' => $payload['ghlSubscriptionId'],
                        'subscriptionSnapshot_size' => is_array($payload['subscriptionSnapshot']) ? count($payload['subscriptionSnapshot']) : 0,
                        'chargeSnapshot_size' => is_array($payload['chargeSnapshot']) ? count($payload['chargeSnapshot']) : 0
                    ]);
                    break;
                    
                default:
                    Log::warning('Unknown event type for webhook payload', [
                        'event' => $event,
                        'locationId' => $locationId
                    ]);
                    return null;
            }
            
            // Validate required fields are present
            $missingFields = [];
            foreach ($payload as $key => $value) {
                if ($value === null || $value === '') {
                    $missingFields[] = $key;
                }
            }
            
            if (!empty($missingFields)) {
                Log::warning('Webhook payload has missing/empty fields', [
                    'event' => $event,
                    'missing_fields' => $missingFields,
                    'payload' => $payload
                ]);
            }
            
            Log::info('Webhook payload built', [
                'event' => $event,
                'payload_fields' => array_keys($payload),
                'has_missing_fields' => !empty($missingFields)
            ]);
            
            return $payload;
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
            $startTime = microtime(true);
            $requestId = uniqid('webhook_', true);
            
            try {
                Log::info('=== WEBHOOK REQUEST RECEIVED ===', [
                    'request_id' => $requestId,
                    'event' => $request->input('event'),
                    'locationId' => $request->input('locationId'),
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'timestamp' => now()->toIso8601String(),
                    'request_headers' => $request->headers->all(),
                    'request_data_keys' => array_keys($request->all())
                ]);
                
                // Log full request data for debugging (be careful with sensitive data)
                $requestData = $request->all();
                if (isset($requestData['apiKey'])) {
                    $requestData['apiKey'] = substr($requestData['apiKey'], 0, 10) . '...';
                }
                Log::debug('Webhook request data (sanitized)', [
                    'request_id' => $requestId,
                    'data' => $requestData
                ]);
                
                $event = $request->input('event');
                $locationId = $request->input('locationId');
                $apiKey = $request->input('apiKey');
                
                // Validate required fields for all events
                Log::info('Step 1: Validating required fields', [
                    'request_id' => $requestId,
                    'has_event' => !empty($event),
                    'has_locationId' => !empty($locationId),
                    'has_apiKey' => !empty($apiKey)
                ]);
                
                if (!$event) {
                    Log::warning('VALIDATION FAILED: Missing event field', [
                        'request_id' => $requestId,
                        'request_data' => $request->all()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Event field is required'
                    ], 400);
                }
                
                if (!$locationId) {
                    Log::warning('VALIDATION FAILED: Missing locationId', [
                        'request_id' => $requestId,
                        'event' => $event
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'locationId is required'
                    ], 400);
                }
                
                if (!$apiKey) {
                    Log::warning('VALIDATION FAILED: Missing apiKey', [
                        'request_id' => $requestId,
                        'event' => $event,
                        'locationId' => $locationId
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'apiKey is required'
                    ], 400);
                }
                
                // Find user by location ID
                Log::info('Step 2: Looking up user by locationId', [
                    'request_id' => $requestId,
                    'locationId' => $locationId
                ]);
                
                $user = User::where('lead_location_id', $locationId)->first();
                if (!$user) {
                    Log::warning('VALIDATION FAILED: User not found for location', [
                        'request_id' => $requestId,
                        'locationId' => $locationId,
                        'event' => $event
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'User not found for locationId: ' . $locationId
                    ], 404);
                }
                
                Log::info('User found', [
                    'request_id' => $requestId,
                    'user_id' => $user->id,
                    'locationId' => $locationId,
                    'tap_mode' => $user->tap_mode
                ]);
                
                // Validate API key matches user's configured key
                $userApiKey = $user->tap_mode === 'live' ? $user->lead_live_api_key : $user->lead_test_api_key;
                
                Log::info('Step 3: Validating API key', [
                    'request_id' => $requestId,
                    'tap_mode' => $user->tap_mode,
                    'provided_key_length' => strlen($apiKey ?? ''),
                    'expected_key_length' => strlen($userApiKey ?? ''),
                    'keys_match' => $apiKey === $userApiKey
                ]);
                
                if ($apiKey !== $userApiKey) {
                    Log::warning('VALIDATION FAILED: API key mismatch', [
                        'request_id' => $requestId,
                        'locationId' => $locationId,
                        'event' => $event,
                        'provided_key_prefix' => substr($apiKey ?? '', 0, 10),
                        'expected_key_prefix' => substr($userApiKey ?? '', 0, 10),
                        'provided_key_length' => strlen($apiKey ?? ''),
                        'expected_key_length' => strlen($userApiKey ?? '')
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid API key'
                    ], 401);
                }
                
                // Validate event-specific required fields and handle event
                Log::info('Step 4: Validating event-specific fields', [
                    'request_id' => $requestId,
                    'event' => $event
                ]);
                
                $validationResult = $this->validateWebhookEvent($request, $event);
                if (!$validationResult['valid']) {
                    Log::warning('VALIDATION FAILED: Event-specific validation failed', [
                        'request_id' => $requestId,
                        'event' => $event,
                        'errors' => $validationResult['errors'],
                        'request_data' => $request->all()
                    ]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validationResult['errors']
                    ], 400);
                }
                
                Log::info('All validations passed', [
                    'request_id' => $requestId,
                    'event' => $event,
                    'locationId' => $locationId
                ]);
                
                // IMPORTANT: Send webhook event to LeadConnector backend BEFORE verification
                Log::info('Step 5: Sending webhook to LeadConnector backend (BEFORE verification)', [
                    'request_id' => $requestId,
                    'event' => $event,
                    'locationId' => $locationId
                ]);
                
                $webhookSent = $this->sendWebhookToLeadConnector($request, $user);
                if (!$webhookSent) {
                    Log::warning('Webhook send to LeadConnector failed, but continuing with verification', [
                        'request_id' => $requestId,
                        'event' => $event,
                        'locationId' => $locationId
                    ]);
                    // Continue processing even if webhook send fails (non-blocking)
                } else {
                    Log::info('Webhook successfully sent to LeadConnector', [
                        'request_id' => $requestId,
                        'event' => $event,
                        'locationId' => $locationId
                    ]);
                }
                
                // Handle different webhook events (this will verify with Tap API)
                Log::info('Step 6: Processing event handler and verifying with Tap API', [
                    'request_id' => $requestId,
                    'event' => $event,
                    'locationId' => $locationId
                ]);
                
                switch ($event) {
                    case 'payment.captured':
                        Log::info('Handling payment.captured event', ['request_id' => $requestId]);
                        $this->handlePaymentCaptured($request, $user);
                        break;
                    case 'subscription.charged':
                        Log::info('Handling subscription.charged event', ['request_id' => $requestId]);
                        $this->handleSubscriptionCharged($request, $user);
                        break;
                    case 'subscription.trialing':
                        Log::info('Handling subscription.trialing event', ['request_id' => $requestId]);
                        $this->handleSubscriptionTrialing($request, $user);
                        break;
                    case 'subscription.active':
                        Log::info('Handling subscription.active event', ['request_id' => $requestId]);
                        $this->handleSubscriptionActive($request, $user);
                        break;
                    case 'subscription.updated':
                        Log::info('Handling subscription.updated event', ['request_id' => $requestId]);
                        $this->handleSubscriptionUpdated($request, $user);
                        break;
                    default:
                        Log::warning('Unhandled webhook event', [
                            'request_id' => $requestId,
                            'event' => $event
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => 'Unhandled event type: ' . $event
                        ], 400);
                }
                
                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2); // milliseconds
                
                Log::info('=== WEBHOOK PROCESSED SUCCESSFULLY ===', [
                    'request_id' => $requestId,
                    'event' => $event,
                    'locationId' => $locationId,
                    'duration_ms' => $duration,
                    'webhook_sent_to_leadconnector' => $webhookSent,
                    'timestamp' => now()->toIso8601String()
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Webhook processed successfully',
                    'event' => $event,
                    'request_id' => $requestId
                ]);
                
            } catch (\Exception $e) {
                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000, 2);
                
                Log::error('=== WEBHOOK PROCESSING ERROR ===', [
                    'request_id' => $requestId ?? 'unknown',
                    'error_message' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                    'error_file' => $e->getFile(),
                    'error_line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'request_data' => $request->all(),
                    'duration_ms' => $duration,
                    'timestamp' => now()->toIso8601String()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Internal server error processing webhook',
                    'request_id' => $requestId ?? 'unknown'
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
                
                Log::info('=== Processing payment.captured event ===', [
                    'chargeId' => $chargeId,
                    'ghlTransactionId' => $ghlTransactionId,
                    'locationId' => $locationId,
                    'user_id' => $user->id,
                    'tap_mode' => $user->tap_mode,
                    'chargeSnapshot_keys' => array_keys($chargeSnapshot)
                ]);
                
                // Extract charge details from snapshot
                $amount = $chargeSnapshot['amount'] ?? null;
                $currency = $chargeSnapshot['currency'] ?? null;
                $status = $chargeSnapshot['status'] ?? null;
                
                Log::info('Extracted charge details from snapshot', [
                    'chargeId' => $chargeId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'status' => $status
                ]);
                
                // Verify the charge with Tap API if chargeId is available
                if ($chargeId) {
                    Log::info('Verifying charge with Tap API', [
                        'chargeId' => $chargeId,
                        'user_id' => $user->id,
                        'tap_mode' => $user->tap_mode
                    ]);
                    
                    $verifyStartTime = microtime(true);
                    $isVerified = $this->verifyChargeWithTap($chargeId, $user);
                    $verifyEndTime = microtime(true);
                    $verifyDuration = round(($verifyEndTime - $verifyStartTime) * 1000, 2);
                    
                    Log::info('=== Charge verification completed ===', [
                        'chargeId' => $chargeId,
                        'verified' => $isVerified,
                        'verification_duration_ms' => $verifyDuration,
                        'timestamp' => now()->toIso8601String()
                    ]);
                } else {
                    Log::warning('No chargeId provided, skipping Tap API verification', [
                        'ghlTransactionId' => $ghlTransactionId,
                        'locationId' => $locationId
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
                
                Log::info('=== Processing subscription.charged event ===', [
                    'chargeId' => $chargeId,
                    'ghlSubscriptionId' => $ghlSubscriptionId,
                    'locationId' => $locationId,
                    'user_id' => $user->id,
                    'tap_mode' => $user->tap_mode,
                    'subscriptionSnapshot_keys' => array_keys($subscriptionSnapshot),
                    'chargeSnapshot_keys' => array_keys($chargeSnapshot)
                ]);
                
                // Extract subscription details
                $subscriptionStatus = $subscriptionSnapshot['status'] ?? null;
                $subscriptionPlan = $subscriptionSnapshot['plan'] ?? null;
                
                // Extract charge details
                $amount = $chargeSnapshot['amount'] ?? null;
                $currency = $chargeSnapshot['currency'] ?? null;
                $chargeStatus = $chargeSnapshot['status'] ?? null;
                
                Log::info('Extracted subscription and charge details', [
                    'subscriptionStatus' => $subscriptionStatus,
                    'subscriptionPlan' => $subscriptionPlan,
                    'amount' => $amount,
                    'currency' => $currency,
                    'chargeStatus' => $chargeStatus
                ]);
                
                // Verify the charge with Tap API if chargeId is available
                if ($chargeId) {
                    Log::info('Verifying subscription charge with Tap API', [
                        'chargeId' => $chargeId,
                        'ghlSubscriptionId' => $ghlSubscriptionId,
                        'user_id' => $user->id,
                        'tap_mode' => $user->tap_mode
                    ]);
                    
                    $verifyStartTime = microtime(true);
                    $isVerified = $this->verifyChargeWithTap($chargeId, $user);
                    $verifyEndTime = microtime(true);
                    $verifyDuration = round(($verifyEndTime - $verifyStartTime) * 1000, 2);
                    
                    Log::info('=== Subscription charge verification completed ===', [
                        'chargeId' => $chargeId,
                        'ghlSubscriptionId' => $ghlSubscriptionId,
                        'verified' => $isVerified,
                        'verification_duration_ms' => $verifyDuration,
                        'timestamp' => now()->toIso8601String()
                    ]);
                } else {
                    Log::warning('No chargeId provided, skipping Tap API verification', [
                        'ghlSubscriptionId' => $ghlSubscriptionId,
                        'locationId' => $locationId
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
                'customer' => $data['customer'] ?? [
                    'first_name' => 'test',
                    'middle_name' => 'test', 
                    'last_name' => 'test',
                    'email' => 'test@test.com',
                    'phone' => ['country_code' => 965, 'number' => 51234567]
                ],
                'source' => $data['source'] ?? ['id' => 'src_all'], // Use src_all for all payment methods
                'post' => $data['post'] ?? ['url' => config('app.url') . '/charge/webhook'],
                'redirect' => $data['redirect'] ?? ['url' => config('app.url') . '/payment/redirect']
            ];
            
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
            // Only add if it's not already in the URL to avoid duplicates
            if ($locationId && isset($tapData['redirect']['url'])) {
                $redirectUrl = $tapData['redirect']['url'];
                
                // Check if locationId is already in the URL
                $urlHasLocationId = strpos($redirectUrl, 'locationId=') !== false;
                
                if (!$urlHasLocationId) {
                    $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
                    $tapData['redirect']['url'] = $redirectUrl . $separator . 'locationId=' . urlencode($locationId);
                    
                    Log::info('Added locationId to redirect URL', [
                        'original_url' => $redirectUrl,
                        'new_url' => $tapData['redirect']['url'],
                        'locationId' => $locationId
                    ]);
                } else {
                    Log::info('locationId already in redirect URL, skipping addition', [
                        'url' => $redirectUrl,
                        'locationId' => $locationId
                    ]);
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
                
                // Log full charge response for debugging
                Log::info('✅ [KNET Detection] Tap charge created successfully', [
                    'charge_id' => $chargeData['id'] ?? 'N/A',
                    'charge_status' => $chargeData['status'] ?? 'N/A',
                    'full_charge_response' => $chargeData
                ]);
                
                // Detect KNET payment method
                $isKnetPayment = $this->detectKnetPayment($chargeData);
                
                // Log transaction details
                $transactionUrl = $chargeData['transaction']['url'] ?? null;
                $sourceInfo = $chargeData['source'] ?? null;
                
                Log::info('🔍 [KNET Detection] Payment method analysis', [
                    'is_knet' => $isKnetPayment,
                    'transaction_url' => $transactionUrl,
                    'source_id' => $sourceInfo['id'] ?? 'N/A',
                    'source_type' => $sourceInfo['type'] ?? 'N/A',
                    'source_object' => $sourceInfo['object'] ?? 'N/A',
                    'full_source' => $sourceInfo,
                    'transaction_object' => $chargeData['transaction'] ?? null,
                    'metadata' => $chargeData['metadata'] ?? null
                ]);
                
                // Check if URL is external (not Tap-hosted)
                $isExternalRedirect = false;
                if ($transactionUrl) {
                    $isTapHosted = str_contains($transactionUrl, 'tap.company') || 
                                   str_contains($transactionUrl, 'tap-payments.com');
                    $isExternalRedirect = !$isTapHosted;
                    
                    Log::info('🔍 [KNET Detection] Transaction URL analysis', [
                        'url' => $transactionUrl,
                        'is_tap_hosted' => $isTapHosted,
                        'is_external_redirect' => $isExternalRedirect,
                        'url_length' => strlen($transactionUrl)
                    ]);
                }
                
                // Log final KNET detection result
                Log::info('📊 [KNET Detection] Final detection result', [
                    'is_knet_detected' => $isKnetPayment,
                    'is_external_redirect' => $isExternalRedirect,
                    'should_use_popup' => $isKnetPayment || $isExternalRedirect,
                    'charge_id' => $chargeData['id'] ?? 'N/A',
                    'location_id' => $locationId,
                    'user_id' => $user->id
                ]);
                
                // Store charge ID in session for popup payment flow
                if (isset($chargeData['id'])) {
                    session(['last_charge_id' => $chargeData['id']]);
                    session(['user_id' => $user->id]);
                    Log::info('💾 [KNET Detection] Stored charge in session', [
                        'charge_id' => $chargeData['id'],
                        'user_id' => $user->id
                    ]);
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
     * Detect if payment method is KNET (external redirect)
     */
    private function detectKnetPayment(array $chargeData): bool
    {
        try {
            Log::info('🔍 [KNET Detection] Starting server-side KNET detection');
            
            // Check if source indicates KNET
            if (isset($chargeData['source'])) {
                $source = $chargeData['source'];
                $sourceId = $source['id'] ?? '';
                $sourceType = $source['type'] ?? '';
                $sourceObject = $source['object'] ?? '';
                
                Log::info('🔍 [KNET Detection] Checking source object', [
                    'source_id' => $sourceId,
                    'source_type' => $sourceType,
                    'source_object' => $sourceObject,
                    'full_source' => $source
                ]);
                
                // If source is src_all, it means all payment methods are available including KNET
                // KNET can be selected from the checkout page and will redirect externally
                // So we should treat src_all as potential KNET payment
                if ($sourceId === 'src_all' || strtolower($sourceId) === 'src_all') {
                    Log::info('✅ [KNET Detection] src_all detected - KNET may be selected (treating as potential KNET)');
                    return true;
                }
                
                // KNET typically has specific identifiers
                if (stripos($sourceId, 'knet') !== false || 
                    stripos($sourceType, 'knet') !== false ||
                    stripos($sourceObject, 'knet') !== false) {
                    Log::info('✅ [KNET Detection] KNET detected from source');
                    return true;
                }
            } else {
                Log::info('⚠️ [KNET Detection] No source object in charge');
            }
            
            // Check transaction URL for KNET indicators
            if (isset($chargeData['transaction']['url'])) {
                $url = strtolower($chargeData['transaction']['url']);
                Log::info('🔍 [KNET Detection] Checking transaction URL', ['url' => $url]);
                
                if (str_contains($url, 'knet') || 
                    str_contains($url, 'redirect') || 
                    str_contains($url, 'external')) {
                    Log::info('✅ [KNET Detection] KNET detected from transaction URL');
                    return true;
                }
            } else {
                Log::info('⚠️ [KNET Detection] No transaction URL in charge');
            }
            
            // Check if payment method in metadata
            if (isset($chargeData['metadata'])) {
                $metadataStr = json_encode($chargeData['metadata']);
                Log::info('🔍 [KNET Detection] Checking metadata', ['metadata' => $chargeData['metadata']]);
                
                if (stripos($metadataStr, 'knet') !== false) {
                    Log::info('✅ [KNET Detection] KNET detected from metadata');
                    return true;
                }
            } else {
                Log::info('⚠️ [KNET Detection] No metadata in charge');
            }
            
            // If transaction URL is external (not Tap-hosted), it's likely KNET or similar redirect payment
            if (isset($chargeData['transaction']['url'])) {
                $url = $chargeData['transaction']['url'];
                $isTapHosted = str_contains($url, 'tap.company') || 
                              str_contains($url, 'tap-payments.com');
                
                Log::info('🔍 [KNET Detection] Checking if URL is Tap-hosted', [
                    'url' => $url,
                    'is_tap_hosted' => $isTapHosted
                ]);
                
                if (!$isTapHosted) {
                    Log::info('✅ [KNET Detection] External redirect detected (not Tap-hosted)');
                    return true;
                }
            }
            
            Log::info('❌ [KNET Detection] No KNET indicators found');
            return false;
        } catch (\Exception $e) {
            Log::error('❌ [KNET Detection] Error detecting KNET payment method', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
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
            
            // Send webhook for final payment states (if transactionId is available)
            // This ensures webhooks are sent even if GoHighLevel doesn't call verify endpoint
            $status = strtoupper($chargeData['status'] ?? 'UNKNOWN');
            $isFinalState = !in_array($status, ['INITIATED', 'PENDING']);
            $transactionId = $chargeData['reference']['transaction'] ?? null;
            
            if ($isFinalState && $transactionId && $user) {
                Log::info('Final payment state detected in getChargeStatus, attempting to send webhook', [
                    'chargeId' => $tapId,
                    'transactionId' => $transactionId,
                    'status' => $status,
                    'locationId' => $user->lead_location_id,
                    'tap_mode' => $user->tap_mode
                ]);
                
                // Use WebhookService to send webhook (shared service)
                try {
                    $webhookService = new \App\Services\WebhookService();
                    $webhookService->sendPaymentCapturedWebhook($user, $tapId, $transactionId, $chargeData);
                } catch (\Exception $e) {
                    Log::warning('Failed to send webhook from getChargeStatus', [
                        'error' => $e->getMessage(),
                        'chargeId' => $tapId,
                        'transactionId' => $transactionId,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
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
            
            Log::info('🔍 [VERIFICATION] Payment verification request received', [
                'type' => $data['type'] ?? 'unknown',
                'transactionId' => $data['transactionId'] ?? 'unknown',
                'chargeId' => $data['chargeId'] ?? 'unknown',
                'apiKey' => $data['apiKey'] ?? 'unknown',
                'subscriptionId' => $data['subscriptionId'] ?? null,
                'request_url' => $request->fullUrl(),
                'request_method' => $request->method(),
                'timestamp' => now()->toIso8601String(),
                'all_request_data' => $data
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
            $verificationStartTime = microtime(true);
            $isPaymentSuccessful = $this->verifyChargeWithTap($chargeId, $user);
            $verificationEndTime = microtime(true);
            $verificationDuration = round(($verificationEndTime - $verificationStartTime) * 1000, 2);

            if ($isPaymentSuccessful) {
                Log::info('✅ [VERIFICATION] Payment verification successful', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'verification_duration_ms' => $verificationDuration,
                    'timestamp' => now()->toIso8601String()
                ]);

                return response()->json([
                    'success' => true
                ]);
            } else {
                Log::warning('❌ [VERIFICATION] Payment verification failed', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'verification_duration_ms' => $verificationDuration,
                    'timestamp' => now()->toIso8601String(),
                    'reason' => 'Charge status is not CAPTURED or AUTHORIZED'
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
            Log::info('=== START: Tap API Charge Verification ===', [
                'chargeId' => $chargeId,
                'user_id' => $user?->id,
                'tap_mode' => $user?->tap_mode,
                'timestamp' => now()->toIso8601String()
            ]);
            
            // If no user provided, we can't verify with Tap API
            if (!$user) {
                Log::error('VERIFICATION FAILED: Cannot verify charge without user context', [
                    'chargeId' => $chargeId
                ]);
                return false;
            }

            // Use the secret key based on the user's stored tap_mode
            $secretKey = $user->tap_mode === 'live' ? $user->lead_live_secret_key : $user->lead_test_secret_key;

            if (!$secretKey) {
                Log::error('VERIFICATION FAILED: No secret key available for user', [
                    'userId' => $user->id,
                    'tap_mode' => $user->tap_mode,
                    'has_live_key' => !empty($user->lead_live_secret_key),
                    'has_test_key' => !empty($user->lead_test_secret_key)
                ]);
                return false;
            }

            Log::info('Preparing Tap API request', [
                'chargeId' => $chargeId,
                'tap_mode' => $user->tap_mode,
                'secret_key_prefix' => substr($secretKey, 0, 15) . '...',
                'api_url' => 'https://api.tap.company/v2/charges/' . $chargeId
            ]);

            // Call Tap API to get charge details with timeout and retry logic
            $apiStartTime = microtime(true);
            $maxRetries = 3;
            $retryDelay = 1; // seconds
            $response = null;
            $lastError = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    Log::info('Tap API verification attempt', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'chargeId' => $chargeId
                    ]);
                    
                    $response = Http::timeout(10) // 10 second timeout
                        ->retry(2, 500) // Retry 2 times with 500ms delay
                        ->withHeaders([
                            'Authorization' => 'Bearer ' . $secretKey,
                            'accept' => 'application/json',
                        ])->get('https://api.tap.company/v2/charges/' . $chargeId);
                    
                    // If successful, break out of retry loop
                    if ($response->successful()) {
                        break;
                    }
                    
                    // If not successful and not last attempt, wait and retry
                    if ($attempt < $maxRetries) {
                        $lastError = $response->json() ?? $response->body();
                        Log::warning('Tap API verification attempt failed, retrying', [
                            'attempt' => $attempt,
                            'status_code' => $response->status(),
                            'error' => $lastError,
                            'will_retry_in_seconds' => $retryDelay
                        ]);
                        sleep($retryDelay);
                    }
                } catch (\Exception $e) {
                    $lastError = $e->getMessage();
                    Log::warning('Tap API verification attempt exception', [
                        'attempt' => $attempt,
                        'error' => $lastError,
                        'will_retry' => $attempt < $maxRetries
                    ]);
                    
                    if ($attempt < $maxRetries) {
                        sleep($retryDelay);
                    }
                }
            }
            
            $apiEndTime = microtime(true);
            $apiDuration = round(($apiEndTime - $apiStartTime) * 1000, 2);

            // Check if we got a response after all retries
            if (!$response) {
                Log::error('=== VERIFICATION FAILED: No response after all retries ===', [
                    'chargeId' => $chargeId,
                    'max_retries' => $maxRetries,
                    'api_duration_ms' => $apiDuration,
                    'last_error' => $lastError,
                    'timestamp' => now()->toIso8601String()
                ]);
                return false;
            }

            Log::info('Tap API response received', [
                'chargeId' => $chargeId,
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'api_duration_ms' => $apiDuration,
                'response_headers' => $response->headers()
            ]);

            if (!$response->successful()) {
                $errorResponse = $response->json() ?? $response->body();
                Log::error('=== VERIFICATION FAILED: Tap API error ===', [
                    'chargeId' => $chargeId,
                    'status_code' => $response->status(),
                    'response_body' => $errorResponse,
                    'api_duration_ms' => $apiDuration,
                    'timestamp' => now()->toIso8601String()
                ]);
                return false;
            }

            $chargeData = $response->json();
            $status = $chargeData['status'] ?? 'UNKNOWN';
            $responseCode = $chargeData['response']['code'] ?? 'unknown';
            $responseMessage = $chargeData['response']['message'] ?? 'unknown';
            $createdAt = $chargeData['created'] ?? null;
            $updatedAt = $chargeData['updated'] ?? null;

            Log::info('Tap API charge data retrieved', [
                'chargeId' => $chargeId,
                'status' => $status,
                'response_code' => $responseCode,
                'response_message' => $responseMessage,
                'amount' => $chargeData['amount'] ?? null,
                'currency' => $chargeData['currency'] ?? null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'api_duration_ms' => $apiDuration,
                'full_charge_data' => $chargeData // Log full data for debugging
            ]);

            // Consider payment successful if status is CAPTURED or AUTHORIZED
            $isVerified = in_array($status, ['CAPTURED', 'AUTHORIZED']);
            
            // Log warning if status is not what we expect for a successful payment
            if (!$isVerified && in_array($status, ['INITIATED', 'PENDING'])) {
                Log::warning('Payment verification: Status is still pending', [
                    'chargeId' => $chargeId,
                    'status' => $status,
                    'message' => 'Payment may still be processing. This could cause verification to fail if checked too early.'
                ]);
            }
            
            Log::info('=== END: Tap API Charge Verification ===', [
                'chargeId' => $chargeId,
                'status' => $status,
                'verified' => $isVerified,
                'is_captured' => $status === 'CAPTURED',
                'is_authorized' => $status === 'AUTHORIZED',
                'is_pending' => in_array($status, ['INITIATED', 'PENDING']),
                'is_failed' => in_array($status, ['FAILED', 'DECLINED', 'CANCELLED', 'REVERSED']),
                'api_duration_ms' => $apiDuration,
                'timestamp' => now()->toIso8601String()
            ]);

            return $isVerified;

        } catch (\Exception $e) {
            Log::error('=== EXCEPTION: Error verifying charge with Tap API ===', [
                'chargeId' => $chargeId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String()
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
     * Decode JWT token and return full payload
     *
     * @param string $token JWT access token
     * @return array|null The decoded token payload, null if decoding fails
     */
    private function decodeJWTToken(string $token): ?array
    {
        try {
            // JWT tokens have three parts: header.payload.signature
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return null;
            }
            
            // Decode the payload (second part)
            $payload = $parts[1];
            
            // Add padding if needed (base64url encoding)
            $padding = strlen($payload) % 4;
            if ($padding) {
                $payload .= str_repeat('=', 4 - $padding);
            }
            
            // Replace URL-safe characters
            $payload = strtr($payload, '-_', '+/');
            
            // Decode base64
            $decoded = base64_decode($payload, true);
            
            if ($decoded === false) {
                return null;
            }
            
            $data = json_decode($decoded, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            
            return $data;
        } catch (\Exception $e) {
            Log::warning('Failed to decode JWT token', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Extract locationId from JWT access token
     * For Company-level bulk installations, locationId is not in the response body
     * but can be found in the JWT token payload as primaryAuthClassId or authClassId
     *
     * @param string $token JWT access token
     * @return string|null The locationId if found, null otherwise
     */
    private function extractLocationIdFromToken(string $token): ?string
    {
        try {
            // JWT tokens have three parts: header.payload.signature
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                Log::warning('Invalid JWT token format', [
                    'parts_count' => count($parts)
                ]);
                return null;
            }
            
            // Decode the payload (second part)
            $payload = $parts[1];
            
            // Add padding if needed (base64url encoding)
            $padding = strlen($payload) % 4;
            if ($padding) {
                $payload .= str_repeat('=', 4 - $padding);
            }
            
            // Replace URL-safe characters
            $payload = strtr($payload, '-_', '+/');
            
            // Decode base64
            $decoded = base64_decode($payload, true);
            
            if ($decoded === false) {
                Log::warning('Failed to decode JWT payload', [
                    'payload' => $payload
                ]);
                return null;
            }
            
            $data = json_decode($decoded, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('Failed to parse JWT payload as JSON', [
                    'error' => json_last_error_msg(),
                    'decoded' => $decoded
                ]);
                return null;
            }
            
            // Try to extract locationId from various possible fields
            // For Location-level tokens: authClassId is the locationId
            // For Company-level tokens: primaryAuthClassId might be the locationId
            // Use the decodeJWTToken method to get full data
            $tokenData = $this->decodeJWTToken($token);
            if (!$tokenData) {
                return null;
            }
            
            $locationId = $tokenData['primaryAuthClassId'] ?? $tokenData['authClassId'] ?? null;
            
            // Only return if authClass is Location, or if we have primaryAuthClassId
            // For Company tokens, we might need to use primaryAuthClassId
            if ($locationId) {
                $authClass = $tokenData['authClass'] ?? null;
                
                Log::info('Extracted locationId from JWT token', [
                    'locationId' => $locationId,
                    'authClass' => $authClass,
                    'primaryAuthClassId' => $data['primaryAuthClassId'] ?? null,
                    'authClassId' => $data['authClassId'] ?? null
                ]);
                
                return $locationId;
            }
            
            Log::warning('Could not find locationId in JWT token payload', [
                'available_keys' => array_keys($data)
            ]);
            
            return null;
            
        } catch (\Exception $e) {
            Log::error('Error extracting locationId from JWT token', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
}
