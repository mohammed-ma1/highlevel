<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sub-account "Catalog" (omAg5Y4omfG3O8KWJMGd) had test mode selected at setup,
 * so every charge went to Tap's sandbox and came back live_mode:false even when
 * the GHL payment link was explicitly set to LIVE. GHL signals the mode per
 * transaction by sending that mode's publishable key.
 */
class TapChargeModeTest extends TestCase
{
    use RefreshDatabase;

    private const LOCATION_ID = 'omAg5Y4omfG3O8KWJMGd';
    private const LIVE_PK = 'pk_live_HyYVabcdefghijklmnopqrst';
    private const TEST_PK = 'pk_test_MnBvCxZaQwErTyUiOpAsDf';
    private const LIVE_SK = 'sk_live_EOjv14yCinN9IGzSlVmx6s3a';
    private const TEST_SK = 'sk_test_kiaxQ4Rt7YuIoPaSdFgHjK';

    /** @param string $storedMode the mode the merchant happened to pick at setup */
    private function merchant(string $storedMode): User
    {
        return User::create([
            'name' => 'Catalog',
            'email' => 'location_catalog@leadconnector.local',
            'password' => Hash::make('secret'),
            'lead_location_id' => self::LOCATION_ID,
            'tap_mode' => $storedMode,
            'tap_merchant_id' => '68069980',
            'lead_live_secret_key' => self::LIVE_SK,
            'lead_test_secret_key' => self::TEST_SK,
            'lead_live_publishable_key' => self::LIVE_PK,
            'lead_test_publishable_key' => self::TEST_PK,
        ]);
    }

    private function charge(?string $publishableKey)
    {
        return $this->postJson('/api/charge/create-tap', array_filter([
            'publishableKey' => $publishableKey,
            'amount' => 9.99,
            'currency' => 'SAR',
            'merchant' => ['id' => '68069980'],
            'metadata' => ['udf3' => 'Location: ' . self::LOCATION_ID],
        ]));
    }

    private function assertTapCalledWith(string $secretKey): void
    {
        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.tap.company')
            && $request->header('Authorization')[0] === 'Bearer ' . $secretKey);
    }

    public function test_live_payment_link_uses_the_live_secret_key_despite_test_being_selected_at_setup(): void
    {
        $this->merchant('test');
        Http::fake(['api.tap.company/*' => Http::response(['id' => 'chg_1', 'live_mode' => true], 200)]);

        $this->charge(self::LIVE_PK)->assertOk();

        $this->assertTapCalledWith(self::LIVE_SK);
    }

    public function test_test_payment_link_uses_the_test_secret_key_despite_live_being_selected_at_setup(): void
    {
        $this->merchant('live');
        Http::fake(['api.tap.company/*' => Http::response(['id' => 'chg_1', 'live_mode' => false], 200)]);

        $this->charge(self::TEST_PK)->assertOk();

        $this->assertTapCalledWith(self::TEST_SK);
    }

    public function test_an_unknown_publishable_key_falls_back_to_its_own_prefix(): void
    {
        $this->merchant('test');
        Http::fake(['api.tap.company/*' => Http::response(['id' => 'chg_1'], 200)]);

        // Rotated in Tap without reconnecting, so it does not match what we stored.
        $this->charge('pk_live_rotatedKeyNotInOurDatabase')->assertOk();

        $this->assertTapCalledWith(self::LIVE_SK);
    }

    public function test_missing_publishable_key_falls_back_to_the_stored_mode(): void
    {
        $this->merchant('test');
        Http::fake(['api.tap.company/*' => Http::response(['id' => 'chg_1'], 200)]);

        $this->charge(null)->assertOk();

        $this->assertTapCalledWith(self::TEST_SK);
    }
}
