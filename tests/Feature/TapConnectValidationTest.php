<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The bad values used here are the ones that actually reached Tap in production
 * and surfaced to buyers as "Failed to create charge with Tap".
 */
class TapConnectValidationTest extends TestCase
{
    use RefreshDatabase;

    private const LOCATION_ID = 'sDqt60KrGb49OZCUvQmu';

    protected function setUp(): void
    {
        parent::setUp();

        User::create([
            'name' => 'Location',
            'email' => 'location_test@leadconnector.local',
            'password' => Hash::make('secret'),
            'lead_location_id' => self::LOCATION_ID,
            'lead_access_token' => 'valid-token',
            'lead_token_expires_at' => now()->addDay(),
        ]);
    }

    private function information(): string
    {
        $state = base64_encode(json_encode(['type' => 'location', 'id' => self::LOCATION_ID]));

        return 'https://app.gohighlevel.com/integration?state=' . $state;
    }

    private function connect(array $overrides = [])
    {
        return $this->post('/provider/connect-or-disconnect', array_merge([
            'action' => 'connect',
            'information' => $this->information(),
            'tap_mode' => 'live',
            'merchant_id' => '68069980',
            'apiKey' => 'XXtapXX',
            'live_secretKey' => 'sk_live_EOjv14yCinN9IGzSlVmx6s3a',
            'live_publishableKey' => 'pk_live_HyYVabcdefghijklmnopqrst',
        ], $overrides));
    }

    public function test_business_name_in_merchant_id_is_rejected(): void
    {
        Http::fake();

        $this->connect(['merchant_id' => 'samialbalhan Coaching'])
            ->assertSessionHasErrors('merchant_id');

        Http::assertNothingSent();
    }

    public function test_business_name_in_secret_key_is_rejected(): void
    {
        Http::fake();

        $this->connect(['live_secretKey' => 'alaatariqalaatariqalaatariq'])
            ->assertSessionHasErrors('live_secretKey');

        Http::assertNothingSent();
    }

    public function test_publishable_key_in_secret_key_field_is_rejected(): void
    {
        Http::fake();

        $this->connect(['live_secretKey' => 'pk_test_EtHFV4BuKq7WmL2Zn9Rs87Y'])
            ->assertSessionHasErrors('live_secretKey');

        Http::assertNothingSent();
    }

    public function test_live_mode_requires_live_credentials(): void
    {
        Http::fake();

        $this->connect(['live_secretKey' => null, 'live_publishableKey' => null])
            ->assertSessionHasErrors(['live_secretKey', 'live_publishableKey']);

        Http::assertNothingSent();
    }

    public function test_test_mode_rejects_live_keys(): void
    {
        Http::fake();

        $this->connect([
            'tap_mode' => 'test',
            'test_secretKey' => 'sk_live_EOjv14yCinN9IGzSlVmx6s3a',
            'test_publishableKey' => 'pk_live_HyYVabcdefghijklmnopqrst',
        ])->assertSessionHasErrors(['test_secretKey', 'test_publishableKey']);

        Http::assertNothingSent();
    }

    public function test_well_formed_credentials_are_accepted_and_stored(): void
    {
        Http::fake([
            'services.leadconnectorhq.com/*' => Http::response(['success' => true], 200),
        ]);

        $this->connect()->assertSessionHasNoErrors();

        $user = User::where('lead_location_id', self::LOCATION_ID)->first();

        $this->assertSame('68069980', $user->tap_merchant_id);
        $this->assertSame('sk_live_EOjv14yCinN9IGzSlVmx6s3a', $user->lead_live_secret_key);
        $this->assertSame('live', $user->tap_mode);
    }
}
