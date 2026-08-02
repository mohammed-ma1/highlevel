<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Helpers around HighLevel's custom payment provider config for a location.
 *
 * POST /payments/custom-provider/provider creates a *fresh* provider config for
 * the location, which drops the live/test credentials previously pushed through
 * POST /payments/custom-provider/connect. Registering the provider for a
 * location that is already connected therefore disconnects it, so every
 * registration call must first check the current state.
 */
class CustomProviderService
{
    /** No provider config exists for the location yet. */
    public const STATE_MISSING = 'missing';

    /** Provider config exists but no test/live credentials are attached. */
    public const STATE_REGISTERED = 'registered';

    /** Provider config exists and has credentials - re-registering would break it. */
    public const STATE_CONNECTED = 'connected';

    /** The state could not be determined (network/permission error). */
    public const STATE_UNKNOWN = 'unknown';

    private const BASE_URL = 'https://services.leadconnectorhq.com/payments/custom-provider';

    private const API_VERSION = '2021-07-28';

    public function state(string $accessToken, string $locationId): string
    {
        try {
            $resp = Http::timeout(15)
                ->acceptJson()
                ->withoutRedirecting()
                ->withToken($accessToken)
                ->withHeaders(['Version' => self::API_VERSION])
                ->get(self::BASE_URL . '/connect?locationId=' . urlencode($locationId));

            $body = $resp->json();

            if ($resp->successful()) {
                $state = $this->hasCredentials($body) ? self::STATE_CONNECTED : self::STATE_REGISTERED;
            } elseif ($resp->status() === 404 || stripos((string) ($body['message'] ?? ''), 'not found') !== false) {
                $state = self::STATE_MISSING;
            } elseif ($resp->status() === 401 || $resp->status() === 403) {
                $state = self::STATE_UNKNOWN;
            } else {
                // Anything else (e.g. "provider is not connected yet") means the
                // base provider config does exist.
                $state = self::STATE_REGISTERED;
            }

            Log::info('🔎 [PROVIDER STATE] Resolved custom provider state', [
                'locationId' => $locationId,
                'state' => $state,
                'status' => $resp->status(),
                'body' => $body ?: $resp->body(),
            ]);

            return $state;
        } catch (\Exception $e) {
            Log::warning('⚠️ [PROVIDER STATE] Could not resolve custom provider state', [
                'locationId' => $locationId,
                'error' => $e->getMessage(),
            ]);

            return self::STATE_UNKNOWN;
        }
    }

    /**
     * Whether it is safe to (re)create the base provider config for a location.
     *
     * $isTargetLocation is true for the location the user is actually installing
     * or configuring right now. Any other location is only ever touched when we
     * positively know it has no provider config at all.
     */
    public function canRegisterProvider(string $state, bool $isTargetLocation = false): bool
    {
        if ($state === self::STATE_CONNECTED) {
            return false;
        }

        if ($isTargetLocation) {
            return true;
        }

        return $state === self::STATE_MISSING;
    }

    /**
     * Re-push the Tap credentials we hold for a location.
     *
     * Used after a provider config is (re)created so a location that was already
     * set up does not silently end up disconnected.
     */
    public function restoreStoredConnection(string $accessToken, string $locationId): bool
    {
        $user = User::where('lead_location_id', $locationId)->first();

        if (!$user) {
            return false;
        }

        $liveApiKey = $user->lead_live_api_key;
        $testApiKey = $user->lead_test_api_key;
        $livePublishableKey = $user->lead_live_publishable_key;
        $testPublishableKey = $user->lead_test_publishable_key;

        $hasLive = $liveApiKey && $livePublishableKey;
        $hasTest = $testApiKey && $testPublishableKey;

        if (!$hasLive && !$hasTest) {
            return false;
        }

        $payload = $this->providerPayload();

        if ($hasLive) {
            $payload['live'] = [
                'apiKey' => $liveApiKey,
                'publishableKey' => $livePublishableKey,
            ];
        }

        if ($hasTest) {
            $payload['test'] = [
                'apiKey' => $testApiKey,
                'publishableKey' => $testPublishableKey,
            ];
        }

        try {
            $resp = Http::timeout(25)
                ->acceptJson()
                ->withoutRedirecting()
                ->withToken($accessToken)
                ->withHeaders(['Version' => self::API_VERSION])
                ->post(self::BASE_URL . '/connect?locationId=' . urlencode($locationId), $payload);

            Log::info('♻️ [PROVIDER RESTORE] Re-pushed stored Tap credentials', [
                'locationId' => $locationId,
                'status' => $resp->status(),
                'successful' => $resp->successful(),
                'restored_live' => $hasLive,
                'restored_test' => $hasTest,
                'body' => $resp->json() ?: $resp->body(),
            ]);

            return $resp->successful();
        } catch (\Exception $e) {
            Log::error('❌ [PROVIDER RESTORE] Failed to re-push stored Tap credentials', [
                'locationId' => $locationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function providerPayload(): array
    {
        return [
            'name' => config('services.tap.provider_name', 'Tap Payments'),
            'description' => config('services.tap.provider_description', 'Innovating payment acceptance & collection in MENA'),
            'paymentsUrl' => config('services.tap.provider_payments_url', 'https://dashboard.mediasolution.io/charge'),
            'queryUrl' => config('services.tap.provider_query_url', 'https://dashboard.mediasolution.io/api/payment/query'),
            'imageUrl' => config('services.tap.provider_image_url', 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg'),
        ];
    }

    private function hasCredentials(?array $body): bool
    {
        if (!is_array($body)) {
            return false;
        }

        // GHL wraps the config in different envelopes depending on the endpoint version.
        foreach (['data', 'config', 'providerConfig'] as $wrapper) {
            if (isset($body[$wrapper]) && is_array($body[$wrapper])) {
                $body = $body[$wrapper];
                break;
            }
        }

        foreach (['live', 'test'] as $mode) {
            $modeConfig = $body[$mode] ?? null;
            if (is_array($modeConfig) && (!empty($modeConfig['apiKey']) || !empty($modeConfig['publishableKey']))) {
                return true;
            }
        }

        return false;
    }
}
