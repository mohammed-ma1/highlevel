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
        $tokenUrl = config('services.external_auth.token_url', 'https://services.leadconnectorhq.com/oauth/token');

        try {
            // 1) Exchange auth code -> token
            $tokenResponse = Http::timeout(15)
                ->acceptJson()
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type'    => 'authorization_code',
                    'client_id'     => config('services.external_auth.client_id'),
                    'client_secret' => config('services.external_auth.client_secret'),
                    'code'          => $request->input('code'),
                    // 'redirect_uri' => config('services.external_auth.redirect_uri'), // recommended to include if required
                ]);

            if ($tokenResponse->failed()) {
                return response()->json([
                    'message' => 'OAuth exchange failed',
                    'status'  => $tokenResponse->status(),
                    'error'   => $tokenResponse->json() ?: $tokenResponse->body(),
                ], 502);
            }

            $body = $tokenResponse->json();

            // Extract what we need
            $accessToken   = $body['access_token'] ?? null;
            $refreshToken  = $body['refresh_token'] ?? null;
            $tokenType     = $body['token_type'] ?? 'Bearer';
            $expiresIn     = (int) ($body['expires_in'] ?? 0);
            $scope         = $body['scope'] ?? null;

            $refreshTokenId = $body['refreshTokenId'] ?? null;
            $userType       = $body['userType'] ?? null;    // "Location"
            $companyId      = $body['companyId'] ?? null;
            $locationId     = $body['locationId'] ?? null;
            $isBulk         = (bool) ($body['isBulkInstallation'] ?? false);
            $providerUserId = $body['userId'] ?? null;

            if (!$accessToken || !$locationId) {
                return response()->json([
                    'message' => 'Invalid OAuth response (missing access_token or locationId)',
                    'json'    => $body,
                ], 502);
            }

            // 2) Find or create a local user tied to this location
            // Prefer to find by lead_location_id (unique per location)
            $user = User::where('lead_location_id', $locationId)->first();

            if (!$user) {
                // No user? Create a minimal one.
                // You need a unique email due to your schema; generate a placeholder.
                $placeholderEmail = "location_{$locationId}@leadconnector.local";

                $user = new User();
                $user->name = "Location {$locationId}";
                $user->email = $placeholderEmail;
                $user->password = Hash::make(Str::random(40));
            }

            // 3) Fill OAuth fields (ensure you added these columns + casts as discussed)
            $user->lead_access_token          = $accessToken;
            $user->lead_refresh_token         = $refreshToken;
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

            $user->save();

            // 4) (Optional) Associate app ↔ location (provider)
            $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
                        . '?locationId=' . urlencode($locationId);

              $providerPayload = [
            'name'        => 'Tap Integration',
            'description' => 'Supports Visa and MasterCard payments via Tap Card SDK, with secure token generation for each transaction. KNET payments redirect customers to the KNET checkout page. The resulting token or KNET source ID is compatible with the Charge API. Note: PayPal is not supported.',
            'paymentsUrl' => 'https://dashboard.mediasolution.io/webhook',
            'queryUrl'    => 'https://dashboard.mediasolution.io/webhook',
            'imageUrl'    => 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg',
            ];




            $providerResp = Http::timeout(20)
                ->acceptJson()
                ->withToken($accessToken)
                ->withHeaders(['Version' => '2021-07-28'])
                ->post($providerUrl, $providerPayload); // usually empty per docs

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
            return response()->json(['message' => 'Could not extract locationId from URL'], 400);
        }

        // 2) Find the user who previously connected this location
        $user = User::where('lead_location_id', $locationId)->first();
        if (!$user) {
            return response()->json([
                'message' => 'No local user found for this locationId.',
                'locationId' => $locationId,
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
        }

        $baseUrl = 'https://services.leadconnectorhq.com/payments/custom-provider';
        $qs      = '?locationId=' . urlencode($locationId);

        if ($action === 'connect') {
            $connectUrl = $baseUrl . '/connect' . $qs;

            $payload = [
                // include provider meta if your flow needs it (these are examples)
                'name'        => 'Company Paypal Integration',
                'description' => 'This payment gateway supports payments in India via UPI, Net banking, cards and wallets.',
                'paymentsUrl' => 'https://dashboard.mediasolution.io/tap',
                'queryUrl'    => 'https://testsubscription.paypal.com',
                'imageUrl'    => 'https://testsubscription.paypal.com',

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

            return response()->json([
                'message'      => 'Provider config created/updated',
                'locationId'   => $locationId,
                'data'         => $resp->json(),
            ]);
        }

        // disconnect
        $disconnectUrl = $baseUrl . '/disconnect' . $qs;

         $payload = [
                // include provider meta if your flow needs it (these are examples)
                'liveMode'        => $request->input('liveMode', false) ? true : false,
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
            Log::info('webhook', ['request' => $request->all()]);
        }
}
