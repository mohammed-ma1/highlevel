<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Reproduces the Navigate Saudi install (location P9Zxe9E1zbvzLzJCqtiO,
 * company ojXtNONmcLR8HZ91BGER): GHL still reported the sub-account as
 * isInstalled:false when the OAuth callback ran, the confirming INSTALL webhook
 * landed 8 seconds later, and the whole install was dropped in between.
 */
class TapInstallRecoveryTest extends TestCase
{
    use RefreshDatabase;

    private const LOCATION_ID = 'P9Zxe9E1zbvzLzJCqtiO';
    private const COMPANY_ID = 'ojXtNONmcLR8HZ91BGER';

    private function information(string $locationId): string
    {
        $state = base64_encode(json_encode(['type' => 'location', 'id' => $locationId]));

        return 'https://dashboard.mediasolution.io/landing?state=' . $state;
    }

    public function test_oauth_keeps_a_location_that_ghl_has_not_flagged_installed_yet(): void
    {
        Http::fake([
            'services.leadconnectorhq.com/oauth/token' => Http::response([
                'access_token' => 'company-access-token',
                'refresh_token' => 'company-refresh-token',
                'expires_in' => 86400,
                'token_type' => 'Bearer',
                'userType' => 'Company',
                'companyId' => self::COMPANY_ID,
                'locationId' => self::COMPANY_ID,
                'isBulkInstallation' => true,
            ], 200),
            'services.leadconnectorhq.com/locations*' => Http::response([], 404),
            'services.leadconnectorhq.com/oauth/installedLocations*' => Http::response([
                'locations' => [[
                    '_id' => self::LOCATION_ID,
                    'name' => 'Navigate Saudi',
                    'isInstalled' => false,
                ]],
                'count' => 1,
            ], 200),
            'services.leadconnectorhq.com/oauth/locationToken' => Http::response([
                'access_token' => 'location-token',
            ], 200),
            'services.leadconnectorhq.com/payments/custom-provider/connect*' => Http::response(
                ['message' => 'Marketplace payment config not found'], 404
            ),
            'services.leadconnectorhq.com/payments/custom-provider/provider*' => Http::response(['id' => 'prov_1'], 200),
        ]);

        $this->get('/connect?code=07ce20f5e242f216071bb11e17f42548ebfbc709');

        $user = User::where('lead_location_id', self::LOCATION_ID)->first();

        $this->assertNotNull($user, 'the sub-account should have been stored despite isInstalled:false');
        $this->assertSame(self::COMPANY_ID, $user->lead_company_id);
        $this->assertNotEmpty($user->lead_access_token);
    }

    public function test_install_webhook_records_the_company_even_without_a_token(): void
    {
        config(['services.external_auth.client_id' => '68323dc0642d285465c0b85a-mdxt9tp5']);
        Http::fake();

        $this->postJson('/api/marketplace-webhook', [
            'type' => 'INSTALL',
            'appId' => '68323dc0642d285465c0b85a',
            'locationId' => self::LOCATION_ID,
            'companyId' => self::COMPANY_ID,
            'installType' => 'Location',
        ])->assertOk()->assertJsonPath('status', 'ok');

        $user = User::where('lead_location_id', self::LOCATION_ID)->first();

        $this->assertNotNull($user, 'the webhook should record the location even with no token');
        $this->assertSame(self::COMPANY_ID, $user->lead_company_id);
        $this->assertEmpty($user->lead_access_token);
    }

    public function test_setup_page_borrows_the_company_token_for_a_mapping_only_row(): void
    {
        // What the INSTALL webhook leaves behind: location known, no token.
        User::create([
            'name' => 'Location',
            'email' => 'location_mapping@leadconnector.local',
            'password' => Hash::make('secret'),
            'lead_location_id' => self::LOCATION_ID,
            'lead_company_id' => self::COMPANY_ID,
        ]);

        // A later OAuth stored the company token.
        User::create([
            'name' => 'Company',
            'email' => 'company_token@leadconnector.local',
            'password' => Hash::make('secret'),
            'lead_company_id' => self::COMPANY_ID,
            'lead_access_token' => 'company-access-token',
            'lead_token_expires_at' => now()->addDay(),
        ]);

        Http::fake([
            'services.leadconnectorhq.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->post('/provider/connect-or-disconnect', [
            'action' => 'connect',
            'information' => $this->information(self::LOCATION_ID),
            'tap_mode' => 'live',
            'merchant_id' => '68069980',
            'apiKey' => 'XXtapXX',
            'live_secretKey' => 'sk_live_EOjv14yCinN9IGzSlVmx6s3a',
            'live_publishableKey' => 'pk_live_HyYVabcdefghijklmnopqrst',
            'test_secretKey' => 'sk_test_kiaxQ4Rt7YuIoPaSdFgHjK',
            'test_publishableKey' => 'pk_test_MnBvCxZaQwErTyUiOpAsDf',
        ])->assertSessionHasNoErrors();

        $user = User::where('lead_location_id', self::LOCATION_ID)->first();

        $this->assertSame('company-access-token', $user->lead_access_token);
        $this->assertSame('68069980', $user->tap_merchant_id);
    }
}
