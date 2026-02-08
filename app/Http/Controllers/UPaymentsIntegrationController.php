<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UPaymentsIntegrationController extends Controller
{
    /**
     * OAuth callback for the UPayments marketplace app.
     * This is intentionally parallel to the existing Tap `/connect` flow and
     * uses its own OAuth client config (`services.external_auth_upayments`).
     */
    public function uconnect(Request $request)
    {
        Log::info('🟣 [UPAYMENTS] === UCONNECT ENDPOINT CALLED ===', [
            'timestamp' => now()->toIso8601String(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'path' => $request->path(),
            'has_code' => $request->has('code'),
            'query_keys' => array_keys($request->query->all()),
        ]);

        if (!$request->has('code')) {
            // Try to capture location selection from referer before OAuth.
            $selectedLocationId = null;
            $referer = $request->header('referer');

            if ($referer && preg_match('/\/location\/([^\/]+)/', $referer, $matches)) {
                $selectedLocationId = $matches[1];
            }

            if (!$selectedLocationId && preg_match('/\/location\/([^\/]+)/', $request->fullUrl(), $matches)) {
                $selectedLocationId = $matches[1];
            }

            if ($selectedLocationId) {
                session(['selected_location_id' => $selectedLocationId]);
                \Cookie::queue('selected_location_id', $selectedLocationId, 10);
            }

            $clientId = config('services.external_auth_upayments.client_id');
            $redirectUri = config(
                'services.external_auth_upayments.redirect_uri',
                'https://dashboard.mediasolution.io/uconnect'
            );

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
                'oauth.readonly',
            ];

            $scopeString = implode(' ', array_map('urlencode', $scopes));

            $oauthUrl = 'https://marketplace.gohighlevel.com/oauth/chooselocation?' . http_build_query([
                'response_type' => 'code',
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'scope' => $scopeString,
                'version_id' => $clientId,
            ]);

            Log::info('🟣 [UPAYMENTS] Redirecting to OAuth authorization', [
                'oauth_url' => $oauthUrl,
                'redirect_uri' => $redirectUri,
            ]);

            return redirect($oauthUrl);
        }

        $tokenUrl = config(
            'services.external_auth_upayments.token_url',
            'https://services.leadconnectorhq.com/oauth/token'
        );

        $clientId = config('services.external_auth_upayments.client_id');
        $clientSecret = config('services.external_auth_upayments.client_secret');
        $redirectUri = config('services.external_auth_upayments.redirect_uri', 'https://dashboard.mediasolution.io/uconnect');

        $tokenPayload = [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $request->input('code'),
            'redirect_uri' => $redirectUri,
        ];

        Log::info('🟣 [UPAYMENTS] OAuth token request', [
            'url' => $tokenUrl,
            'client_id' => $clientId,
            'has_client_secret' => !empty($clientSecret),
            'redirect_uri' => $redirectUri,
        ]);

        $tokenResponse = Http::timeout(15)
            ->acceptJson()
            ->asForm()
            ->post($tokenUrl, $tokenPayload);

        if ($tokenResponse->failed()) {
            Log::error('🟣 [UPAYMENTS] OAuth token exchange failed', [
                'status' => $tokenResponse->status(),
                'body' => $tokenResponse->body(),
                'json' => $tokenResponse->json(),
            ]);

            return response()->json([
                'message' => 'OAuth exchange failed (UPayments app)',
                'status' => $tokenResponse->status(),
            ], 502);
        }

        $body = $tokenResponse->json() ?? [];

        $accessToken = $body['access_token'] ?? null;
        $refreshToken = $body['refresh_token'] ?? null;
        $tokenType = $body['token_type'] ?? 'Bearer';
        $expiresIn = (int)($body['expires_in'] ?? 0);
        $scope = $body['scope'] ?? null;

        $companyId = $body['companyId'] ?? null;
        $authLocationId = $body['locationId'] ?? null;
        $userType = $body['userType'] ?? null;
        $refreshTokenId = $body['refreshTokenId'] ?? ($body['refresh_token_id'] ?? null);
        $userId = $body['userId'] ?? null;
        $isBulkInstallation = (bool)($body['isBulkInstallation'] ?? false);

        // If the API didn't return a locationId, try our session/cookie fallback.
        $selectedLocationId = session('selected_location_id') ?? $request->cookie('selected_location_id');

        // IMPORTANT: For bulk/company installs, GHL commonly returns a companyId in `locationId`.
        // Tap's integration handles this by fetching installed locations and picking a real locationId.
        $finalLocationId = null;
        $companyAuthClassId = null;
        if ($userType === 'Company' && $isBulkInstallation) {
            $companyAuthClassId = $authLocationId ?: $companyId;
            $finalLocationId = $this->pickFirstInstalledLocationId($accessToken, $companyAuthClassId, $clientId)
                ?: $selectedLocationId
                ?: $companyAuthClassId;

            Log::info('🟣 [UPAYMENTS] Bulk/company install location selection', [
                'userType' => $userType,
                'isBulk' => $isBulkInstallation,
                'companyAuthClassId' => $companyAuthClassId,
                'selectedLocationId' => $selectedLocationId,
                'finalLocationId' => $finalLocationId,
            ]);
        } else {
            $finalLocationId = $authLocationId ?: $selectedLocationId;
        }

        if (!$finalLocationId) {
            Log::error('🟣 [UPAYMENTS] No locationId available after OAuth', [
                'response_keys' => array_keys($body),
                'has_selected_location_id' => !empty($selectedLocationId),
            ]);

            return response()->json([
                'message' => 'OAuth succeeded but locationId is missing (UPayments app)',
            ], 502);
        }

        // Create/update user for that location (UPayments OAuth stored separately to avoid affecting Tap).
        $user = User::where('lead_location_id', $finalLocationId)->first();
        if (!$user) {
            $baseEmail = "location_{$finalLocationId}@leadconnector.local";
            $placeholderEmail = $baseEmail;
            $counter = 1;
            while (User::where('email', $placeholderEmail)->exists()) {
                $placeholderEmail = "location_{$finalLocationId}_{$counter}@leadconnector.local";
                $counter++;
            }

            $user = new User();
            $user->name = "Location {$finalLocationId}";
            $user->email = $placeholderEmail;
            $user->password = Hash::make(Str::random(40));
        }

        // Store UPayments OAuth in dedicated columns
        $user->upayments_lead_access_token = $accessToken;
        $user->upayments_lead_refresh_token = $refreshToken;
        $user->upayments_lead_token_type = $tokenType;
        $user->upayments_lead_expires_in = $expiresIn ?: null;
        $user->upayments_lead_token_expires_at = $expiresIn ? now()->addSeconds($expiresIn) : null;
        $user->upayments_lead_scope = is_array($scope) ? json_encode($scope) : $scope;

        $user->upayments_lead_refresh_token_id = $refreshTokenId;
        $user->upayments_lead_user_type = $userType;
        $user->upayments_lead_company_id = $companyId;
        $user->upayments_lead_location_id = $finalLocationId;
        $user->upayments_lead_user_id = $userId;
        $user->upayments_lead_is_bulk_installation = $isBulkInstallation;

        // Keep the shared location mapping used across the app
        $user->lead_location_id = $finalLocationId;

        $user->save();

        Log::info('🟣 [UPAYMENTS] OAuth saved successfully', [
            'user_id' => $user->id,
            'lead_location_id' => $user->lead_location_id,
            'upayments_lead_location_id' => $user->upayments_lead_location_id,
            'upayments_lead_company_id' => $user->upayments_lead_company_id,
            'upayments_lead_user_type' => $user->upayments_lead_user_type,
            'is_bulk_installation' => $user->upayments_lead_is_bulk_installation,
        ]);

        // === IMPORTANT (matches Tap connect behavior): Register provider so integration appears in GHL ===
        // For bulk/company installs, register provider for each installed location using location-scoped token.
        try {
            $this->registerCustomProvider(
                $accessToken,
                $companyId,
                $userType,
                $isBulkInstallation,
                $finalLocationId,
                $clientId,
                $companyAuthClassId
            );
        } catch (\Exception $e) {
            Log::warning('🟣 [UPAYMENTS] Provider registration threw exception (continuing)', [
                'error' => $e->getMessage(),
            ]);
        }

        // Redirect to GHL location integrations page (same destination pattern as Tap flow).
        $redirectUrl = "https://app.gohighlevel.com/v2/location/{$finalLocationId}/payments/integrations";
        return redirect($redirectUrl);
    }

    private function registerCustomProvider(
        ?string $accessToken,
        ?string $companyId,
        ?string $userType,
        bool $isBulk,
        string $locationId,
        string $clientId,
        ?string $companyAuthClassId = null
    ): void
    {
        if (empty($accessToken)) {
            Log::warning('🟣 [UPAYMENTS] Skipping provider registration: missing access token');
            return;
        }

        $providerPayload = [
            'name' => 'UPayments',
            'description' => 'Hosted checkout (Non-Whitelabel) via UPayments',
            'paymentsUrl' => 'https://dashboard.mediasolution.io/ucharge',
            'queryUrl' => 'https://dashboard.mediasolution.io/api/upayment/query',
            'imageUrl' => 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg',
        ];

        // Bulk/company install: register provider for all installed locations
        if ($userType === 'Company' && $isBulk) {
            $appId = explode('-', $clientId)[0] ?? $clientId;

            $installedLocationsUrl = "https://services.leadconnectorhq.com/oauth/installedLocations";
            $resp = Http::timeout(30)
                ->acceptJson()
                ->withToken($accessToken)
                ->withHeaders(['Version' => '2021-07-28'])
                ->get($installedLocationsUrl, [
                    'companyId' => $companyAuthClassId ?: $locationId,
                    'appId' => $appId,
                    'limit' => 100,
                ]);

            if (!$resp->successful()) {
                Log::warning('🟣 [UPAYMENTS] installedLocations fetch failed; falling back to single location provider registration', [
                    'status' => $resp->status(),
                    'body' => $resp->json() ?: $resp->body(),
                ]);
                $this->registerProviderForLocation($accessToken, $locationId, $providerPayload);
                return;
            }

            $locationsData = $resp->json() ?? [];
            $locations = $locationsData['locations'] ?? $locationsData['data'] ?? $locationsData ?? [];
            if (!is_array($locations)) {
                $locations = [];
            }

            $locationsToRegister = [];
            foreach ($locations as $loc) {
                $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
                $isInstalled = $loc['isInstalled'] ?? false;
                if ($isInstalled && $locId) {
                    $locationsToRegister[] = $locId;
                }
            }

            foreach ($locationsToRegister as $actualLocationId) {
                $tokenToUse = $accessToken;
                if ($companyId) {
                    $locationToken = $this->getLocationAccessToken($accessToken, $companyId, $actualLocationId);
                    if ($locationToken) {
                        $tokenToUse = $locationToken;
                    }
                }
                $this->registerProviderForLocation($tokenToUse, $actualLocationId, $providerPayload);
            }

            return;
        }

        // Location-level install: register provider for this location
        $this->registerProviderForLocation($accessToken, $locationId, $providerPayload);
    }

    private function registerProviderForLocation(string $token, string $locationId, array $payload): void
    {
        $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
            . '?locationId=' . urlencode($locationId);

        $resp = Http::timeout(40)
            ->acceptJson()
            ->withToken($token)
            ->withHeaders(['Version' => '2021-07-28'])
            ->post($providerUrl, $payload);

        Log::info('🟣 [UPAYMENTS] Provider registration response', [
            'locationId' => $locationId,
            'status' => $resp->status(),
            'successful' => $resp->successful(),
            'body' => $resp->json() ?: $resp->body(),
        ]);

        // Optional verification (Tap does a similar sanity check when responses look suspicious)
        if ($resp->successful()) {
            try {
                $verifyResp = Http::timeout(10)
                    ->acceptJson()
                    ->withToken($token)
                    ->withHeaders(['Version' => '2021-07-28'])
                    ->get($providerUrl);
                Log::info('🟣 [UPAYMENTS] Provider verification response', [
                    'locationId' => $locationId,
                    'status' => $verifyResp->status(),
                    'successful' => $verifyResp->successful(),
                    'body' => $verifyResp->json() ?: $verifyResp->body(),
                ]);
            } catch (\Exception $e) {
                Log::warning('🟣 [UPAYMENTS] Provider verification exception', [
                    'locationId' => $locationId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function getLocationAccessToken(string $companyAccessToken, string $companyId, string $locationId): ?string
    {
        try {
            $tokenUrl = 'https://services.leadconnectorhq.com/oauth/locationToken';
            $response = Http::timeout(30)
                ->acceptJson()
                ->withToken($companyAccessToken)
                ->withHeaders(['Version' => '2021-07-28'])
                ->post($tokenUrl, [
                    'companyId' => $companyId,
                    'locationId' => $locationId,
                ]);

            if ($response->successful()) {
                $data = $response->json() ?? [];
                return $data['access_token'] ?? null;
            }

            Log::warning('🟣 [UPAYMENTS] Failed to get location-scoped token', [
                'status' => $response->status(),
                'body' => $response->json() ?: $response->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::warning('🟣 [UPAYMENTS] Exception getting location-scoped token', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * For bulk/company installs, pick a real installed locationId (isInstalled: true).
     * Mirrors Tap's strategy of using the first installed location.
     */
    private function pickFirstInstalledLocationId(?string $accessToken, ?string $companyAuthClassId, string $clientId): ?string
    {
        if (empty($accessToken) || empty($companyAuthClassId)) {
            return null;
        }

        $appId = explode('-', $clientId)[0] ?? $clientId;
        $installedLocationsUrl = "https://services.leadconnectorhq.com/oauth/installedLocations";

        $resp = Http::timeout(30)
            ->acceptJson()
            ->withToken($accessToken)
            ->withHeaders(['Version' => '2021-07-28'])
            ->get($installedLocationsUrl, [
                'companyId' => $companyAuthClassId,
                'appId' => $appId,
                'limit' => 100,
            ]);

        if (!$resp->successful()) {
            Log::warning('🟣 [UPAYMENTS] pickFirstInstalledLocationId: installedLocations failed', [
                'status' => $resp->status(),
                'body' => $resp->json() ?: $resp->body(),
            ]);
            return null;
        }

        $data = $resp->json() ?? [];
        $locations = $data['locations'] ?? $data['data'] ?? $data ?? [];
        if (!is_array($locations)) {
            return null;
        }

        foreach ($locations as $loc) {
            $locId = $loc['_id'] ?? $loc['id'] ?? $loc['locationId'] ?? null;
            $isInstalled = $loc['isInstalled'] ?? false;
            if ($isInstalled && $locId) {
                return $locId;
            }
        }

        return null;
    }
}

