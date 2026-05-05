<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UPaymentsMarketplaceWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        $type = $payload['type'] ?? null;

        Log::info('🟣 [UPAYMENTS WEBHOOK] Marketplace webhook received', [
            'type' => $type,
            'appId' => $payload['appId'] ?? null,
            'locationId' => $payload['locationId'] ?? null,
            'companyId' => $payload['companyId'] ?? null,
            'installType' => $payload['installType'] ?? null,
            'webhookId' => $payload['webhookId'] ?? null,
        ]);

        if ($this->usesTapOAuthClient()) {
            Log::error('🟣 [UPAYMENTS WEBHOOK] Refusing to process with Tap OAuth client config', [
                'upayments_client_id' => config('services.external_auth_upayments.client_id'),
                'tap_client_id' => config('services.external_auth.client_id'),
            ]);

            return response()->json([
                'status' => 'ignored',
                'message' => 'UPayments webhook requires a dedicated marketplace app client.',
            ], 500);
        }

        if (!$this->isUPaymentsAppEvent($payload)) {
            Log::warning('🟣 [UPAYMENTS WEBHOOK] Ignoring non-UPayments marketplace event', [
                'received_app_id' => $payload['appId'] ?? $payload['app_id'] ?? null,
                'expected_app_id' => $this->configuredUPaymentsAppId(),
                'type' => $type,
            ]);

            return response()->json(['status' => 'ignored', 'message' => 'Not a UPayments marketplace event']);
        }

        return match ($type) {
            'INSTALL' => $this->handleInstall($payload),
            'UNINSTALL' => $this->handleUninstall($payload),
            'LocationUpdate' => $this->handleLocationUpdate($payload),
            default => response()->json(['status' => 'ignored', 'type' => $type]),
        };
    }

    private function handleInstall(array $payload)
    {
        $locationId = $payload['locationId'] ?? null;
        $companyId = $payload['companyId'] ?? null;
        $installType = $payload['installType'] ?? null;

        if (!$locationId) {
            Log::warning('🟣 [UPAYMENTS WEBHOOK] INSTALL missing locationId', $payload);
            return response()->json(['status' => 'error', 'message' => 'Missing locationId']);
        }

        Log::info('🟣 [UPAYMENTS WEBHOOK] Processing INSTALL', [
            'locationId' => $locationId,
            'companyId' => $companyId,
            'installType' => $installType,
            'companyName' => $payload['companyName'] ?? null,
        ]);

        // Try to register the provider for this location as a backup.
        // The primary registration happens during OAuth, but if that failed
        // this ensures the provider still appears in Payments > Integrations.
        $user = User::where('lead_location_id', $locationId)->first();

        if (!$user) {
            Log::info('🟣 [UPAYMENTS WEBHOOK] No user found yet for location (OAuth may not have completed)', [
                'locationId' => $locationId,
            ]);
            return response()->json(['status' => 'ok', 'message' => 'No user yet, OAuth pending']);
        }

        $accessToken = $user->upayments_lead_access_token;
        if (!$accessToken) {
            Log::info('🟣 [UPAYMENTS WEBHOOK] User exists but no access token yet', [
                'locationId' => $locationId,
            ]);
            return response()->json(['status' => 'ok', 'message' => 'Token pending']);
        }

        // Check if token needs refresh
        $expiresAt = $user->upayments_lead_token_expires_at;
        if ($expiresAt && now()->gte($expiresAt)) {
            $refreshToken = $user->upayments_lead_refresh_token;
            if ($refreshToken) {
                $new = $this->refreshToken($refreshToken);
                if ($new['access_token'] ?? null) {
                    $user->upayments_lead_access_token = $new['access_token'];
                    $user->upayments_lead_refresh_token = $new['refresh_token'] ?? $refreshToken;
                    $user->upayments_lead_expires_in = $new['expires_in'] ?? null;
                    $user->upayments_lead_token_expires_at = isset($new['expires_in'])
                        ? now()->addSeconds((int)$new['expires_in'])
                        : null;
                    $user->save();
                    $accessToken = $user->upayments_lead_access_token;
                }
            }
        }

        $tokenToUse = $accessToken;
        if (($user->upayments_lead_user_type ?? null) === 'Company' && !empty($companyId)) {
            $locationToken = $this->getLocationAccessToken($accessToken, $companyId, $locationId);
            if ($locationToken) {
                $tokenToUse = $locationToken;
            }
        }

        $registered = $this->registerProviderForLocation($tokenToUse, $locationId);

        Log::info('🟣 [UPAYMENTS WEBHOOK] INSTALL provider registration result', [
            'locationId' => $locationId,
            'registered' => $registered,
        ]);

        return response()->json([
            'status' => 'ok',
            'providerRegistered' => $registered,
        ]);
    }

    private function handleUninstall(array $payload)
    {
        $locationId = $payload['locationId'] ?? null;

        Log::info('🟣 [UPAYMENTS WEBHOOK] Processing UNINSTALL', [
            'locationId' => $locationId,
            'companyId' => $payload['companyId'] ?? null,
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function handleLocationUpdate(array $payload)
    {
        $locationId = $payload['locationId'] ?? null;

        Log::info('🟣 [UPAYMENTS WEBHOOK] Processing LocationUpdate', [
            'locationId' => $locationId,
        ]);

        return response()->json(['status' => 'ok']);
    }

    private function registerProviderForLocation(string $token, string $locationId): bool
    {
        $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
            . '?locationId=' . urlencode($locationId);

        $providerPayload = $this->upaymentsProviderPayload();

        try {
            $resp = Http::timeout(40)
                ->acceptJson()
                ->withoutRedirecting()
                ->withToken($token)
                ->withHeaders(['Version' => '2021-07-28'])
                ->post($providerUrl, $providerPayload);

            Log::info('🟣 [UPAYMENTS WEBHOOK] Provider registration response', [
                'locationId' => $locationId,
                'status' => $resp->status(),
                'successful' => $resp->successful(),
                'body' => $resp->json() ?: $resp->body(),
            ]);

            return $resp->successful();
        } catch (\Exception $e) {
            Log::error('🟣 [UPAYMENTS WEBHOOK] Provider registration exception', [
                'locationId' => $locationId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function getLocationAccessToken(string $companyAccessToken, string $companyId, string $locationId): ?string
    {
        try {
            $resp = Http::timeout(30)
                ->acceptJson()
                ->withToken($companyAccessToken)
                ->withHeaders(['Version' => '2021-07-28'])
                ->post('https://services.leadconnectorhq.com/oauth/locationToken', [
                    'companyId' => $companyId,
                    'locationId' => $locationId,
                ]);

            if ($resp->successful()) {
                return ($resp->json() ?? [])['access_token'] ?? null;
            }
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function refreshToken(string $refreshToken): array
    {
        $resp = Http::timeout(15)
            ->acceptJson()
            ->asForm()
            ->post(config('services.external_auth_upayments.token_url', 'https://services.leadconnectorhq.com/oauth/token'), [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.external_auth_upayments.client_id'),
                'client_secret' => config('services.external_auth_upayments.client_secret'),
                'refresh_token' => $refreshToken,
            ]);

        return $resp->successful() ? ($resp->json() ?? []) : [];
    }

    private function upaymentsProviderPayload(): array
    {
        return [
            'name' => config('services.upayments.provider_name', 'UPayments'),
            'description' => config('services.upayments.provider_description', 'Hosted checkout (Non-Whitelabel) via UPayments'),
            'paymentsUrl' => config('services.upayments.provider_payments_url', 'https://dashboard.mediasolution.io/ucharge'),
            'queryUrl' => config('services.upayments.provider_query_url', 'https://dashboard.mediasolution.io/api/upayment/query'),
            'imageUrl' => config('services.upayments.provider_image_url', 'https://my.upayments.com/images/upaymentsLogo.png'),
        ];
    }

    private function isUPaymentsAppEvent(array $payload): bool
    {
        $receivedAppId = (string)($payload['appId'] ?? $payload['app_id'] ?? '');
        $expectedAppId = $this->configuredUPaymentsAppId();

        if ($receivedAppId === '' || $expectedAppId === '') {
            return false;
        }

        return $receivedAppId === $expectedAppId
            || str_starts_with($receivedAppId, $expectedAppId . '-');
    }

    private function configuredUPaymentsAppId(): string
    {
        $clientId = (string)config('services.external_auth_upayments.client_id', '');

        return explode('-', $clientId)[0] ?? $clientId;
    }

    private function usesTapOAuthClient(): bool
    {
        $upaymentsClientId = (string)config('services.external_auth_upayments.client_id', '');
        $tapClientId = (string)config('services.external_auth.client_id', '');

        return $upaymentsClientId !== '' && $tapClientId !== '' && $upaymentsClientId === $tapClientId;
    }
}
