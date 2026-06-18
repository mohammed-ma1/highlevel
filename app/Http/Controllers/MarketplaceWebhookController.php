<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Handles GoHighLevel Marketplace app lifecycle webhooks (INSTALL / UNINSTALL)
 * for the Tap Payments marketplace app.
 *
 * Why this exists:
 * During a Company/bulk OAuth install, the OAuth callback often does not carry
 * the selected locationId, and the `oauth/installedLocations` API can still
 * report the just-installed location as `isInstalled: false` (a race condition).
 * That makes the OAuth flow skip provider registration for the location the user
 * actually selected, so the integration never appears in Payments > Integrations.
 *
 * The Marketplace INSTALL webhook always carries the exact locationId, so we use
 * it as the authoritative trigger to register the custom provider (base config)
 * for that location.
 */
class MarketplaceWebhookController extends Controller
{
    private const PROVIDER_ENDPOINT = 'https://services.leadconnectorhq.com/payments/custom-provider/provider';
    private const CONNECT_ENDPOINT = 'https://services.leadconnectorhq.com/payments/custom-provider/connect';
    private const LOCATION_TOKEN_ENDPOINT = 'https://services.leadconnectorhq.com/oauth/locationToken';

    public function handle(Request $request)
    {
        $payload = $request->all();
        $type = $payload['type'] ?? null;

        Log::info('🔵 [TAP WEBHOOK] Marketplace webhook received', [
            'type' => $type,
            'appId' => $payload['appId'] ?? $payload['app_id'] ?? null,
            'locationId' => $payload['locationId'] ?? null,
            'companyId' => $payload['companyId'] ?? null,
            'installType' => $payload['installType'] ?? null,
            'webhookId' => $payload['webhookId'] ?? null,
        ]);

        if (!$this->isTapAppEvent($payload)) {
            Log::warning('🔵 [TAP WEBHOOK] Ignoring non-Tap marketplace event', [
                'received_app_id' => $payload['appId'] ?? $payload['app_id'] ?? null,
                'expected_app_id' => $this->configuredTapAppId(),
                'type' => $type,
            ]);

            return response()->json(['status' => 'ignored', 'message' => 'Not a Tap marketplace event']);
        }

        return match ($type) {
            'INSTALL' => $this->handleInstall($payload),
            'UNINSTALL' => $this->handleUninstall($payload),
            default => response()->json(['status' => 'ignored', 'type' => $type]),
        };
    }

    private function handleInstall(array $payload)
    {
        $locationId = $payload['locationId'] ?? null;
        $companyId = $payload['companyId'] ?? null;

        if (!$locationId) {
            Log::warning('🔵 [TAP WEBHOOK] INSTALL missing locationId', $payload);
            return response()->json(['status' => 'error', 'message' => 'Missing locationId']);
        }

        Log::info('🔵 [TAP WEBHOOK] Processing INSTALL', [
            'locationId' => $locationId,
            'companyId' => $companyId,
            'installType' => $payload['installType'] ?? null,
            'companyName' => $payload['companyName'] ?? null,
        ]);

        // Resolve a usable company-level access token. The location's own user may
        // not exist yet (OAuth may not have completed), so fall back to any sibling
        // user from the same company that already holds a valid token.
        $tokenContext = $this->resolveCompanyToken($locationId, $companyId);

        if (!$tokenContext) {
            Log::info('🔵 [TAP WEBHOOK] No usable access token yet for company/location - deferring to OAuth flow', [
                'locationId' => $locationId,
                'companyId' => $companyId,
            ]);
            return response()->json(['status' => 'ok', 'message' => 'No token yet, OAuth pending']);
        }

        [$companyAccessToken, $resolvedCompanyId] = $tokenContext;

        // Prefer a location-scoped token so the provider is registered at the
        // location level (company-scoped tokens register at company level and the
        // integration will not appear in the sub-account's Integrations tab).
        $tokenToUse = $companyAccessToken;
        if ($resolvedCompanyId) {
            $locationToken = $this->getLocationAccessToken($companyAccessToken, $resolvedCompanyId, $locationId);
            if ($locationToken) {
                $tokenToUse = $locationToken;
            }
        }

        $registered = $this->registerProviderForLocation($tokenToUse, $locationId);

        if ($registered) {
            $this->upsertLocationUser($locationId, $resolvedCompanyId, $companyAccessToken);
        }

        Log::info('🔵 [TAP WEBHOOK] INSTALL provider registration result', [
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

        Log::info('🔵 [TAP WEBHOOK] Processing UNINSTALL', [
            'locationId' => $locationId,
            'companyId' => $payload['companyId'] ?? null,
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Find a company-level access token we can use to mint a location token.
     *
     * @return array{0:string,1:?string}|null [accessToken, companyId]
     */
    private function resolveCompanyToken(string $locationId, ?string $companyId): ?array
    {
        // 1) The location may already have a user row with a token.
        $user = User::where('lead_location_id', $locationId)->first();

        // 2) Otherwise reuse any sibling user from the same company.
        if ((!$user || empty($user->lead_access_token)) && $companyId) {
            $user = User::where('lead_company_id', $companyId)
                ->whereNotNull('lead_access_token')
                ->latest('updated_at')
                ->first();
        }

        if (!$user || empty($user->lead_access_token)) {
            return null;
        }

        $accessToken = $user->lead_access_token;

        // Refresh if expired.
        if ($user->lead_token_expires_at && now()->gte($user->lead_token_expires_at) && $user->lead_refresh_token) {
            $refreshed = $this->refreshToken($user->lead_refresh_token);
            if (!empty($refreshed['access_token'])) {
                $user->lead_access_token = $refreshed['access_token'];
                $user->lead_refresh_token = $refreshed['refresh_token'] ?? $user->lead_refresh_token;
                $user->lead_expires_in = $refreshed['expires_in'] ?? null;
                $user->lead_token_expires_at = isset($refreshed['expires_in'])
                    ? now()->addSeconds((int) $refreshed['expires_in'])
                    : null;
                $user->save();
                $accessToken = $user->lead_access_token;
            }
        }

        return [$accessToken, $companyId ?: $user->lead_company_id];
    }

    private function registerProviderForLocation(string $token, string $locationId): bool
    {
        $providerUrl = self::PROVIDER_ENDPOINT . '?locationId=' . urlencode($locationId);
        $providerPayload = $this->tapProviderPayload();

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $resp = Http::timeout(40)
                    ->acceptJson()
                    ->withoutRedirecting()
                    ->withToken($token)
                    ->withHeaders(['Version' => '2021-07-28'])
                    ->post($providerUrl, $providerPayload);

                Log::info('🔵 [TAP WEBHOOK] Provider registration response', [
                    'locationId' => $locationId,
                    'attempt' => $attempt,
                    'status' => $resp->status(),
                    'successful' => $resp->successful(),
                    'body' => $resp->json() ?: $resp->body(),
                ]);

                if ($resp->successful()) {
                    if ($this->verifyProviderRegistration($token, $locationId)) {
                        return true;
                    }
                    Log::warning('🔵 [TAP WEBHOOK] Provider POST succeeded but verification failed', [
                        'locationId' => $locationId,
                        'attempt' => $attempt,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('🔵 [TAP WEBHOOK] Provider registration exception', [
                    'locationId' => $locationId,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($attempt < 2) {
                sleep(2);
            }
        }

        return false;
    }

    private function verifyProviderRegistration(string $token, string $locationId): bool
    {
        try {
            sleep(1);

            $resp = Http::timeout(15)
                ->acceptJson()
                ->withoutRedirecting()
                ->withToken($token)
                ->withHeaders(['Version' => '2021-07-28'])
                ->get(self::CONNECT_ENDPOINT . '?locationId=' . urlencode($locationId));

            Log::info('🔵 [TAP WEBHOOK] Provider verification (GET /connect)', [
                'locationId' => $locationId,
                'status' => $resp->status(),
                'successful' => $resp->successful(),
                'body' => $resp->json() ?: $resp->body(),
            ]);

            if ($resp->successful()) {
                return true;
            }

            // "Marketplace payment config not found" means the provider does not exist.
            $message = (string) (($resp->json() ?? [])['message'] ?? '');
            if (stripos($message, 'not found') !== false) {
                return false;
            }

            // Any other error (e.g. "not connected yet") means the provider IS registered.
            return $resp->status() !== 404;
        } catch (\Exception $e) {
            Log::warning('🔵 [TAP WEBHOOK] Provider verification exception', [
                'locationId' => $locationId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function upsertLocationUser(string $locationId, ?string $companyId, string $accessToken): void
    {
        try {
            $user = User::where('lead_location_id', $locationId)->first();

            if (!$user) {
                $baseEmail = "location_{$locationId}@leadconnector.local";
                $placeholderEmail = $baseEmail;
                $counter = 1;
                while (User::where('email', $placeholderEmail)->exists()) {
                    $placeholderEmail = "location_{$locationId}_{$counter}@leadconnector.local";
                    $counter++;
                }

                $user = new User();
                $user->name = "Location {$locationId}";
                $user->email = $placeholderEmail;
                $user->password = Hash::make(Str::random(40));
                $user->lead_access_token = $accessToken;
                $user->lead_user_type = 'Location';
                $user->lead_company_id = $companyId;
                $user->lead_location_id = $locationId;
                $user->save();

                Log::info('🔵 [TAP WEBHOOK] Created user row for location', [
                    'locationId' => $locationId,
                    'user_id' => $user->id,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('🔵 [TAP WEBHOOK] Failed to upsert location user', [
                'locationId' => $locationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getLocationAccessToken(string $companyAccessToken, string $companyId, string $locationId): ?string
    {
        try {
            $resp = Http::timeout(30)
                ->acceptJson()
                ->withToken($companyAccessToken)
                ->withHeaders(['Version' => '2021-07-28'])
                ->post(self::LOCATION_TOKEN_ENDPOINT, [
                    'companyId' => $companyId,
                    'locationId' => $locationId,
                ]);

            if ($resp->successful()) {
                return ($resp->json() ?? [])['access_token'] ?? null;
            }

            Log::warning('🔵 [TAP WEBHOOK] Failed to get location-scoped token', [
                'companyId' => $companyId,
                'locationId' => $locationId,
                'status' => $resp->status(),
                'body' => $resp->json() ?: $resp->body(),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::warning('🔵 [TAP WEBHOOK] Location token exception', [
                'companyId' => $companyId,
                'locationId' => $locationId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function refreshToken(string $refreshToken): array
    {
        try {
            $resp = Http::timeout(15)
                ->acceptJson()
                ->asForm()
                ->post(config('services.external_auth.token_url', 'https://services.leadconnectorhq.com/oauth/token'), [
                    'grant_type' => 'refresh_token',
                    'client_id' => config('services.external_auth.client_id'),
                    'client_secret' => config('services.external_auth.client_secret'),
                    'refresh_token' => $refreshToken,
                ]);

            return $resp->successful() ? ($resp->json() ?? []) : [];
        } catch (\Exception $e) {
            Log::warning('🔵 [TAP WEBHOOK] Token refresh exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    private function tapProviderPayload(): array
    {
        return [
            'name' => config('services.tap.provider_name', 'Tap Payments'),
            'description' => config('services.tap.provider_description', 'Innovating payment acceptance & collection in MENA'),
            'paymentsUrl' => config('services.tap.provider_payments_url', 'https://dashboard.mediasolution.io/charge'),
            'queryUrl' => config('services.tap.provider_query_url', 'https://dashboard.mediasolution.io/api/payment/query'),
            'imageUrl' => config('services.tap.provider_image_url', 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg'),
        ];
    }

    private function isTapAppEvent(array $payload): bool
    {
        $receivedAppId = (string) ($payload['appId'] ?? $payload['app_id'] ?? '');
        $expectedAppId = $this->configuredTapAppId();

        // If we cannot determine the expected app id, do not block processing.
        if ($expectedAppId === '') {
            return true;
        }

        if ($receivedAppId === '') {
            return true;
        }

        return $receivedAppId === $expectedAppId
            || str_starts_with($receivedAppId, $expectedAppId . '-');
    }

    private function configuredTapAppId(): string
    {
        $clientId = (string) config('services.external_auth.client_id', '');

        return explode('-', $clientId)[0] ?? $clientId;
    }
}
