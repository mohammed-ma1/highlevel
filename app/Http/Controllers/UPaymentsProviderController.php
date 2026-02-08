<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UPaymentsProviderController extends Controller
{
    public function connectOrDisconnect(Request $request)
    {
        try {
            Log::info('🟣 [UPAYMENTS] Provider connectOrDisconnect called', [
                'action' => $request->input('action'),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'all_params_keys' => array_keys($request->all()),
            ]);

            $action = strtolower((string)$request->input('action'));
            if (!in_array($action, ['connect', 'disconnect'], true)) {
                return response()->json(['message' => 'Invalid action'], 400);
            }

            $information = $request->input('information');
            if (!$information) {
                return response()->json([
                    'message' => 'Missing information URL',
                ], 400);
            }

            parse_str(parse_url($information, PHP_URL_QUERY) ?? '', $query);

            $locationId = null;
            if (isset($query['state'])) {
                $decoded = base64_decode($query['state'], true);
                if ($decoded !== false) {
                    $json = json_decode($decoded, true);
                    if (is_array($json) && isset($json['id'])) {
                        $locationId = $json['id'];
                    }
                }
            }

            if (!$locationId) {
                Log::error('🟣 [UPAYMENTS] Could not extract locationId from information parameter', [
                    'information' => $information,
                ]);
                return response()->json([
                    'message' => 'Could not extract locationId from URL. Please open this page from the correct GHL integration flow.',
                ], 400);
            }

            $user = User::where('lead_location_id', $locationId)->first();
            if (!$user) {
                return response()->json([
                    'message' => 'No user found for this location. Please complete the OAuth integration first by visiting /uconnect.',
                ], 404);
            }

            // Ensure LeadConnector token exists / refresh if needed.
            $accessToken = $user->lead_access_token;
            $refreshToken = $user->lead_refresh_token;
            $expiresAt = $user->lead_token_expires_at;

            if (!$accessToken || ($expiresAt && now()->gte($expiresAt))) {
                if (!$refreshToken) {
                    return response()->json([
                        'message' => 'Stored token expired and no refresh_token available. Re-connect required via /uconnect.',
                    ], 401);
                }

                $new = $this->refreshLeadConnectorToken($refreshToken);
                if (!($new['access_token'] ?? null)) {
                    return response()->json([
                        'message' => 'Token refresh failed',
                    ], 502);
                }

                $user->lead_access_token = $new['access_token'];
                $user->lead_refresh_token = $new['refresh_token'] ?? $refreshToken;
                $user->lead_expires_in = $new['expires_in'] ?? null;
                $user->lead_token_expires_at = isset($new['expires_in']) ? now()->addSeconds((int)$new['expires_in']) : null;
                $user->save();

                $accessToken = $user->lead_access_token;
            }

            if ($action === 'connect') {
                $request->validate([
                    'upayments_mode' => ['required', 'in:test,live'],
                    'upayments_test_token' => ['nullable', 'string'],
                    'upayments_live_token' => ['nullable', 'string'],
                ]);

                $mode = $request->input('upayments_mode', 'test');
                $testToken = (string)$request->input('upayments_test_token', '');
                $liveToken = (string)$request->input('upayments_live_token', '');

                if ($mode === 'test' && $testToken === '') {
                    return redirect()->back()->with([
                        'api_error' => 'Please provide UPayments Test Token.',
                    ])->withInput($request->only('information'));
                }
                if ($mode === 'live' && $liveToken === '') {
                    return redirect()->back()->with([
                        'api_error' => 'Please provide UPayments Live Token.',
                    ])->withInput($request->only('information'));
                }

                $user->upayments_mode = $mode;
                if ($testToken !== '') {
                    $user->upayments_test_token = $testToken;
                }
                if ($liveToken !== '') {
                    $user->upayments_live_token = $liveToken;
                }
                $user->save();

                $tokenToUse = $accessToken;
                $usingLocationToken = false;

                if (!empty($user->lead_company_id)) {
                    $locationToken = $this->getLocationAccessToken($accessToken, $user->lead_company_id, $locationId);
                    if ($locationToken) {
                        $tokenToUse = $locationToken;
                        $usingLocationToken = true;
                    }
                }

                $baseUrl = 'https://services.leadconnectorhq.com/payments/custom-provider';
                $connectUrl = $baseUrl . '/connect' . '?locationId=' . urlencode($locationId);

                $payload = [
                    'name' => 'UPayments Integration',
                    'description' => 'Hosted checkout (Non-Whitelabel) via UPayments. Creates a payment link and redirects the customer to the secure UPayments checkout page.',
                    'paymentsUrl' => 'https://dashboard.mediasolution.io/ucharge',
                    'queryUrl' => 'https://dashboard.mediasolution.io/api/upayment/query',
                    // Reuse existing image slot; can be replaced with an official logo later.
                    'imageUrl' => 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg',
                    'live' => [
                        'apiKey' => $liveToken ?: $testToken,
                        'publishableKey' => $liveToken ?: $testToken,
                    ],
                    'test' => [
                        'apiKey' => $testToken ?: $liveToken,
                        'publishableKey' => $testToken ?: $liveToken,
                    ],
                ];

                Log::info('🟣 [UPAYMENTS] Calling GHL /connect API', [
                    'url' => $connectUrl,
                    'token_type' => $usingLocationToken ? 'location-scoped' : 'stored',
                    'locationId' => $locationId,
                    'mode' => $mode,
                ]);

                $resp = Http::timeout(25)
                    ->acceptJson()
                    ->withoutRedirecting()
                    ->withToken($tokenToUse)
                    ->withHeaders(['Version' => '2021-07-28'])
                    ->post($connectUrl, $payload);

                if ($resp->status() >= 300 && $resp->status() < 400) {
                    return response()->json([
                        'message' => 'LeadConnector /connect returned redirect (blocked)',
                        'status' => $resp->status(),
                        'location' => $resp->header('Location'),
                        'body' => $resp->body(),
                    ], 502);
                }

                if ($resp->failed()) {
                    Log::error('🟣 [UPAYMENTS] GHL connect failed', [
                        'status' => $resp->status(),
                        'body' => $resp->json() ?: $resp->body(),
                    ]);
                    return redirect()->back()->with([
                        'api_error' => json_encode($resp->json() ?: ['error' => $resp->body()]),
                    ])->withInput($request->only('information'));
                }

                return redirect()->back()->with([
                    'success' => true,
                    'message' => 'UPayments provider connected successfully',
                    'locationId' => $locationId,
                    'api_response' => $resp->json(),
                ])->withInput($request->only('information'));
            }

            // disconnect
            $disconnectUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/disconnect' . '?locationId=' . urlencode($locationId);
            $disconnectMode = $request->input('disconnect_mode', 'test');
            $payload = [
                'liveMode' => $disconnectMode === 'live',
            ];

            $resp = Http::timeout(20)
                ->acceptJson()
                ->withoutRedirecting()
                ->withToken($accessToken)
                ->withHeaders(['Version' => '2021-07-28'])
                ->post($disconnectUrl, $payload);

            if ($resp->status() >= 300 && $resp->status() < 400) {
                return response()->json([
                    'message' => 'LeadConnector /disconnect returned redirect (blocked)',
                    'status' => $resp->status(),
                    'location' => $resp->header('Location'),
                    'body' => $resp->body(),
                ], 502);
            }

            if ($resp->failed()) {
                return response()->json([
                    'message' => 'LeadConnector disconnect failed',
                    'status' => $resp->status(),
                    'error' => $resp->json() ?: $resp->body(),
                ], 502);
            }

            return response()->json([
                'message' => 'Provider config disconnected',
                'locationId' => $locationId,
                'disconnect_mode' => $disconnectMode,
                'data' => $resp->json(),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->with([
                'api_error' => json_encode($e->errors()),
            ])->withInput($request->only('information'));
        } catch (\Exception $e) {
            Log::error('🟣 [UPAYMENTS] Provider connectOrDisconnect unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function refreshLeadConnectorToken(string $refreshToken): array
    {
        $tokenUrl = config('services.external_auth_upayments.token_url', 'https://services.leadconnectorhq.com/oauth/token');

        $resp = Http::timeout(15)
            ->acceptJson()
            ->asForm()
            ->post($tokenUrl, [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.external_auth_upayments.client_id'),
                'client_secret' => config('services.external_auth_upayments.client_secret'),
                'refresh_token' => $refreshToken,
            ]);

        if ($resp->failed()) {
            Log::warning('🟣 [UPAYMENTS] Token refresh failed', [
                'status' => $resp->status(),
                'body' => $resp->json() ?: $resp->body(),
            ]);
            return [];
        }

        return $resp->json() ?? [];
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
}

