<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A sub-account can have the app installed without ever getting its own user row
 * (agency install stores only the first reported location, and the INSTALL webhook
 * bails out when no token exists yet). The setup page used to answer those
 * merchants with "No user found for this location".
 */
class TapLocationAdoptionTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_LOCATION = 'P9Zxe9E1zbvzLzJCqtiO';
    private const COMPANY_ID = 'company_abc';

    private function siblingInstall(): User
    {
        return User::create([
            'name' => 'Sibling Location',
            'email' => 'location_sibling@leadconnector.local',
            'password' => Hash::make('secret'),
            'lead_location_id' => 'siblingLocation123',
            'lead_company_id' => self::COMPANY_ID,
            'lead_access_token' => 'company-token',
            'lead_refresh_token' => 'company-refresh',
            'lead_token_expires_at' => now()->addDay(),
        ]);
    }

    private function information(string $locationId): string
    {
        $state = base64_encode(json_encode(['type' => 'location', 'id' => $locationId]));

        return 'https://app.gohighlevel.com/integration?state=' . $state;
    }

    private function connect(string $locationId)
    {
        return $this->post('/provider/connect-or-disconnect', [
            'action' => 'connect',
            'information' => $this->information($locationId),
            'tap_mode' => 'live',
            'merchant_id' => '68069980',
            'apiKey' => 'XXtapXX',
            'live_secretKey' => 'sk_live_EOjv14yCinN9IGzSlVmx6s3a',
            'live_publishableKey' => 'pk_live_HyYVabcdefghijklmnopqrst',
            'test_secretKey' => 'sk_test_kiaxQ4Rt7YuIoPaSdFgHjK',
            'test_publishableKey' => 'pk_test_MnBvCxZaQwErTyUiOpAsDf',
        ]);
    }

    public function test_location_is_adopted_when_company_can_mint_a_location_token(): void
    {
        $this->siblingInstall();

        Http::fake([
            'services.leadconnectorhq.com/oauth/locationToken' => Http::response(['access_token' => 'location-token'], 200),
            // provider config already exists, so no re-registration should happen
            'services.leadconnectorhq.com/payments/custom-provider/connect*' => Http::response(['live' => ['apiKey' => 'x']], 200),
        ]);

        $this->connect(self::NEW_LOCATION)->assertSessionHasNoErrors();

        $adopted = User::where('lead_location_id', self::NEW_LOCATION)->first();

        $this->assertNotNull($adopted, 'the location should have been adopted');
        $this->assertSame(self::COMPANY_ID, $adopted->lead_company_id);
        $this->assertSame('68069980', $adopted->tap_merchant_id);
        $this->assertSame('sk_live_EOjv14yCinN9IGzSlVmx6s3a', $adopted->lead_live_secret_key);
    }

    public function test_base_provider_is_created_when_the_adopted_location_has_none(): void
    {
        $this->siblingInstall();

        Http::fake([
            'services.leadconnectorhq.com/oauth/locationToken' => Http::response(['access_token' => 'location-token'], 200),
            'services.leadconnectorhq.com/payments/custom-provider/connect*' => Http::response(['message' => 'Marketplace payment config not found'], 404),
            'services.leadconnectorhq.com/payments/custom-provider/provider*' => Http::response(['id' => 'prov_1'], 200),
        ]);

        $this->connect(self::NEW_LOCATION)->assertSessionHasNoErrors();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/custom-provider/provider')
            && str_contains($request->url(), self::NEW_LOCATION));
    }

    public function test_unknown_location_is_still_rejected(): void
    {
        $this->siblingInstall();

        Http::fake([
            // the app is not installed on this location, so no token can be minted
            'services.leadconnectorhq.com/oauth/locationToken' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        $this->connect('someLocationNotInstalled')
            ->assertStatus(404)
            ->assertJsonPath('error', 'User not found - OAuth integration required');

        $this->assertNull(User::where('lead_location_id', 'someLocationNotInstalled')->first());
    }

    public function test_adoption_is_skipped_when_the_location_already_has_a_row(): void
    {
        $this->siblingInstall();

        User::create([
            'name' => 'Existing',
            'email' => 'location_existing@leadconnector.local',
            'password' => Hash::make('secret'),
            'lead_location_id' => self::NEW_LOCATION,
            'lead_company_id' => self::COMPANY_ID,
            'lead_access_token' => 'own-token',
            'lead_token_expires_at' => now()->addDay(),
        ]);

        Http::fake([
            'services.leadconnectorhq.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->connect(self::NEW_LOCATION)->assertSessionHasNoErrors();

        $this->assertSame(1, User::where('lead_location_id', self::NEW_LOCATION)->count());
    }
}
