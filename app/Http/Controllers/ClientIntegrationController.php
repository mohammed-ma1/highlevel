<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClientIntegrationController extends Controller
{
    public function connect(Request $request)
    {
        // 1) First: get OAuth token
        $tokenUrl = config('services.external_auth.token_url', 'https://services.leadconnectorhq.com/oauth/token');
        try {
            $tokenResponse = Http::timeout(15)
                ->acceptJson()
                ->asForm()
                ->post($tokenUrl, [
                    'grant_type'    => 'authorization_code',
                    'client_id'     => config('services.external_auth.client_id'),
                    'client_secret' => config('services.external_auth.client_secret'),
                    'code'          => $request->input('code')
                ]);



            $accessToken = $tokenResponse->json('access_token');
           
            $locationId = $tokenResponse->json('locationId');

            // 2) Now: call LeadConnector custom provider API with this token
            $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
                         . '?locationId=' . urlencode($locationId);

            $providerPayload = [
                'name'        => 'Company Paypal Integration',
                'description' => 'This payment gateway supports payments in India via UPI, Net banking, cards and wallets.',
                'paymentsUrl' => 'https://testpayment.paypal.com',
                'queryUrl'    => 'https://testsubscription.paypal.com',
                'imageUrl'    => 'https://testsubscription.paypal.com',
            ];

            $providerResponse = Http::timeout(20)
                ->acceptJson()
                ->withToken($accessToken)
               
                ->withHeaders([
                    'Version'      => '2021-07-28',
                    'Content-Type' => 'application/json',
                ])
                ->post($providerUrl, $providerPayload);
        } catch (\Throwable $e) {
            Log::error('Integration failed', ['error' => $e->getMessage()]);
        }
        $accessToken  = $tokenResponse->json('access_token');
        $refreshToken = $tokenResponse->json('refresh_token'); // if provided
        $expiresIn    = (int) $tokenResponse->json('expires_in', 3600);
        $locationId   = $tokenResponse->json('locationId');
        session()->put('leadconnector', [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at'    => now()->addSeconds($expiresIn),
            'location_id'   => $locationId,
        ]);
    }

     public function connectOrDisconnect(Request $request)
    {
        // Which action was clicked?
        $action = $request->input('action'); // 'connect' or 'disconnect'      

        // Additional fields required only for CONNECT
        if ($action === 'connect') {
            $request->validate([
                'live_apiKey' => ['required_without:test_apiKey', 'string'],
                'live_publishableKey' => ['required_without:test_publishableKey', 'string'],
                'test_apiKey' => ['required_without:live_apiKey', 'string'],
                'test_publishableKey' => ['required_without:live_publishableKey', 'string'],
            ]);
        }

         // Prefer route param; fallback to session
        $locationId   = $locationId ?? session('leadconnector.location_id');
        $accessToken  = session('leadconnector.access_token');
        $expiresAt    = session('leadconnector.expires_at');
        $refreshToken = session('leadconnector.refresh_token');

        if (!$locationId) {
            return response()->json(['message' => 'Missing locationId'], 400);
        }

        // If token missing/expired, refresh if you have a refresh_token (recommended)
        if (!$accessToken || (isset($expiresAt) && now()->gte($expiresAt))) {
            if ($refreshToken) {
                $new = $this->refreshLeadConnectorToken($refreshToken);
                if (!$new['access_token'] ?? null) {
                    return response()->json(['message' => 'Unable to refresh token'], 401);
                }
                // update session
                session()->put('leadconnector', [
                    'access_token'  => $new['access_token'],
                    'refresh_token' => $new['refresh_token'] ?? $refreshToken,
                    'expires_at'    => now()->addSeconds($new['expires_in'] ?? 3600),
                    'location_id'   => $locationId,
                ]);
                $accessToken = $new['access_token'];
            } else {
                return response()->json(['message' => 'Access token expired; re-connect required'], 401);
            }
        }
       

        // 2) LeadConnector: connect OR disconnect
        $baseUrl = 'https://services.leadconnectorhq.com/payments/custom-provider';
        $locationQuery = '?locationId='.urlencode($locationId);

            if ($action === 'connect') {
                $connectUrl = $baseUrl.'/connect'.$locationQuery;
                
                $payload = [
                    'live' => [
                        'apiKey'          => $request->input('live_apiKey'),
                        'publishableKey'  => $request->input('live_publishableKey'),
                    ],
                    'test' => [
                        'apiKey'          => $request->input('test_apiKey'),
                        'publishableKey'  => $request->input('test_publishableKey'),
                    ],
                ];

                $resp = Http::timeout(20)
                    ->acceptJson()
                    ->withToken($accessToken)
                    ->withHeaders([
                        'Version'      => '2021-07-28',
                        'Content-Type' => 'application/json',
                    ])
                    ->post($connectUrl, $payload);
                    dd($resp->json());
            } else { // disconnect
                // If your docs use another method/path, adjust here.
                $disconnectUrl = $baseUrl.'/disconnect'.$locationQuery;

                $resp = Http::timeout(20)
                    ->acceptJson()
                    ->withToken($accessToken)
                    ->withHeaders([
                        'Version'      => '2021-07-28',
                        'Content-Type' => 'application/json',
                    ])
                    ->post($disconnectUrl, []); // some APIs use DELETE; LeadConnector may use POST as per pattern
                                        dd($resp->json());

            }

       
    }

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

}
