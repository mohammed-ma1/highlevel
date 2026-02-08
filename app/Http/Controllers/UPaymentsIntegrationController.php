<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $locationId = $body['locationId'] ?? null;
        $userType = $body['userType'] ?? null;
        $refreshTokenId = $body['refreshTokenId'] ?? ($body['refresh_token_id'] ?? null);
        $userId = $body['userId'] ?? null;
        $isBulkInstallation = (bool)($body['isBulkInstallation'] ?? false);

        // If the API didn't return a locationId, try our session/cookie fallback.
        $selectedLocationId = session('selected_location_id') ?? $request->cookie('selected_location_id');
        $finalLocationId = $locationId ?: $selectedLocationId;

        if (!$finalLocationId) {
            Log::error('🟣 [UPAYMENTS] No locationId available after OAuth', [
                'response_keys' => array_keys($body),
                'has_selected_location_id' => !empty($selectedLocationId),
            ]);

            return response()->json([
                'message' => 'OAuth succeeded but locationId is missing (UPayments app)',
            ], 502);
        }

        // Create/update user for that location.
        $user = User::where('lead_location_id', $finalLocationId)->first();
        if (!$user) {
            $user = new User();
            $user->email = 'location_' . $finalLocationId . '@example.local';
            $user->password = 'not-used';
        }

        $user->lead_access_token = $accessToken;
        $user->lead_refresh_token = $refreshToken;
        $user->lead_token_type = $tokenType;
        $user->lead_expires_in = $expiresIn ?: null;
        $user->lead_token_expires_at = $expiresIn ? now()->addSeconds($expiresIn) : null;
        $user->lead_scope = is_array($scope) ? json_encode($scope) : $scope;

        $user->lead_refresh_token_id = $refreshTokenId;
        $user->lead_user_type = $userType;
        $user->lead_company_id = $companyId;
        $user->lead_location_id = $finalLocationId;
        $user->lead_user_id = $userId;
        $user->lead_is_bulk_installation = $isBulkInstallation;

        $user->save();

        Log::info('🟣 [UPAYMENTS] OAuth saved successfully', [
            'user_id' => $user->id,
            'lead_location_id' => $user->lead_location_id,
            'lead_company_id' => $user->lead_company_id,
            'lead_user_type' => $user->lead_user_type,
            'is_bulk_installation' => $user->lead_is_bulk_installation,
        ]);

        // Redirect to GHL location integrations page (same destination pattern as Tap flow).
        $redirectUrl = "https://app.gohighlevel.com/v2/location/{$finalLocationId}/payments/integrations";
        return redirect($redirectUrl);
    }
}

