<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Manually register the Tap custom payment provider for a specific GHL location.
 *
 * Useful when the OAuth bulk-install flow skipped a location (e.g. GHL reported
 * it as isInstalled:false at callback time) and you need to make the integration
 * appear in Payments > Integrations on demand.
 *
 * Usage:
 *   php artisan tap:register-provider {locationId} [--company=COMPANY_ID]
 */
class RegisterTapProvider extends Command
{
    protected $signature = 'tap:register-provider {locationId : The GHL sub-account (location) id} {--company= : Company id (optional; inferred from stored users if omitted)}';

    protected $description = 'Register the Tap custom payment provider for a specific GHL location';

    public function handle(): int
    {
        $locationId = (string) $this->argument('locationId');
        $companyId = $this->option('company');

        $this->info("Registering Tap provider for location: {$locationId}");

        // Find a stored company-level token to mint a location token.
        $tokenUser = User::where('lead_location_id', $locationId)
            ->whereNotNull('lead_access_token')
            ->first();

        if (!$tokenUser && $companyId) {
            $tokenUser = User::where('lead_company_id', $companyId)
                ->whereNotNull('lead_access_token')
                ->latest('updated_at')
                ->first();
        }

        if (!$tokenUser) {
            $tokenUser = User::whereNotNull('lead_access_token')
                ->latest('updated_at')
                ->first();
        }

        if (!$tokenUser) {
            $this->error('No stored user with a lead_access_token found. Install the app first.');
            return self::FAILURE;
        }

        $companyId = $companyId ?: $tokenUser->lead_company_id;
        $accessToken = $tokenUser->lead_access_token;

        // Refresh if expired.
        if ($tokenUser->lead_token_expires_at && now()->gte($tokenUser->lead_token_expires_at) && $tokenUser->lead_refresh_token) {
            $this->line('Stored token expired - refreshing...');
            $refreshed = $this->refreshToken($tokenUser->lead_refresh_token);
            if (!empty($refreshed['access_token'])) {
                $tokenUser->lead_access_token = $refreshed['access_token'];
                $tokenUser->lead_refresh_token = $refreshed['refresh_token'] ?? $tokenUser->lead_refresh_token;
                $tokenUser->lead_expires_in = $refreshed['expires_in'] ?? null;
                $tokenUser->lead_token_expires_at = isset($refreshed['expires_in'])
                    ? now()->addSeconds((int) $refreshed['expires_in'])
                    : null;
                $tokenUser->save();
                $accessToken = $tokenUser->lead_access_token;
            }
        }

        // Prefer a location-scoped token.
        $tokenToUse = $accessToken;
        if ($companyId) {
            $this->line("Minting location-scoped token (company: {$companyId})...");
            $locationToken = $this->getLocationAccessToken($accessToken, $companyId, $locationId);
            if ($locationToken) {
                $tokenToUse = $locationToken;
                $this->info('Got location-scoped token.');
            } else {
                $this->warn('Could not mint location token; using stored token.');
            }
        }

        $providerUrl = 'https://services.leadconnectorhq.com/payments/custom-provider/provider'
            . '?locationId=' . urlencode($locationId);

        $payload = [
            'name' => config('services.tap.provider_name', 'Tap Payments'),
            'description' => config('services.tap.provider_description', 'Innovating payment acceptance & collection in MENA'),
            'paymentsUrl' => config('services.tap.provider_payments_url', 'https://dashboard.mediasolution.io/charge'),
            'queryUrl' => config('services.tap.provider_query_url', 'https://dashboard.mediasolution.io/api/payment/query'),
            'imageUrl' => config('services.tap.provider_image_url', 'https://msgsndr-private.storage.googleapis.com/marketplace/apps/68323dc0642d285465c0b85a/11524e13-1e69-41f4-a378-54a4c8e8931a.jpg'),
        ];

        $resp = Http::timeout(40)
            ->acceptJson()
            ->withoutRedirecting()
            ->withToken($tokenToUse)
            ->withHeaders(['Version' => '2021-07-28'])
            ->post($providerUrl, $payload);

        $this->line('Status: ' . $resp->status());
        $this->line('Body: ' . $resp->body());

        if (!$resp->successful()) {
            $this->error('Provider registration FAILED.');
            return self::FAILURE;
        }

        $this->info('Provider registered successfully.');

        // Ensure a user row exists for this location.
        $locationUser = User::where('lead_location_id', $locationId)->first();
        if (!$locationUser) {
            $placeholderEmail = "location_{$locationId}@leadconnector.local";
            $counter = 1;
            while (User::where('email', $placeholderEmail)->exists()) {
                $placeholderEmail = "location_{$locationId}_{$counter}@leadconnector.local";
                $counter++;
            }
            $locationUser = new User();
            $locationUser->name = "Location {$locationId}";
            $locationUser->email = $placeholderEmail;
            $locationUser->password = Hash::make(Str::random(40));
            $locationUser->lead_access_token = $accessToken;
            $locationUser->lead_refresh_token = $tokenUser->lead_refresh_token;
            $locationUser->lead_user_type = 'Location';
            $locationUser->lead_company_id = $companyId;
            $locationUser->lead_location_id = $locationId;
            $locationUser->save();
            $this->info("Created user row (id: {$locationUser->id}) for location.");
        }

        return self::SUCCESS;
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

            $this->warn('locationToken failed: ' . $resp->status() . ' ' . $resp->body());
            return null;
        } catch (\Exception $e) {
            $this->warn('locationToken exception: ' . $e->getMessage());
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
            return [];
        }
    }
}
