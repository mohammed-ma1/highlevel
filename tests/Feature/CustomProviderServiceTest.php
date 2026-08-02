<?php

namespace Tests\Feature;

use App\Services\CustomProviderService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CustomProviderServiceTest extends TestCase
{
    private const CONNECT_URL = 'https://services.leadconnectorhq.com/payments/custom-provider/connect*';

    public function test_location_with_credentials_is_reported_as_connected(): void
    {
        Http::fake([
            self::CONNECT_URL => Http::response([
                'live' => ['apiKey' => 'sk_live_x', 'publishableKey' => 'pk_live_x'],
                'test' => ['apiKey' => '', 'publishableKey' => ''],
            ], 200),
        ]);

        $this->assertSame(
            CustomProviderService::STATE_CONNECTED,
            (new CustomProviderService())->state('token', 'loc_a')
        );
    }

    public function test_missing_provider_config_is_reported_as_missing(): void
    {
        Http::fake([
            self::CONNECT_URL => Http::response(['message' => 'Marketplace payment config not found'], 404),
        ]);

        $this->assertSame(
            CustomProviderService::STATE_MISSING,
            (new CustomProviderService())->state('token', 'loc_a')
        );
    }

    public function test_registered_but_unconnected_provider_is_reported_as_registered(): void
    {
        Http::fake([
            self::CONNECT_URL => Http::response(['message' => 'Provider is not connected yet'], 400),
        ]);

        $this->assertSame(
            CustomProviderService::STATE_REGISTERED,
            (new CustomProviderService())->state('token', 'loc_a')
        );
    }

    public function test_connected_locations_are_never_re_registered(): void
    {
        $service = new CustomProviderService();

        $this->assertFalse($service->canRegisterProvider(CustomProviderService::STATE_CONNECTED, true));
        $this->assertFalse($service->canRegisterProvider(CustomProviderService::STATE_CONNECTED, false));
    }

    public function test_bystander_locations_are_only_registered_when_config_is_missing(): void
    {
        $service = new CustomProviderService();

        $this->assertTrue($service->canRegisterProvider(CustomProviderService::STATE_MISSING, false));
        $this->assertFalse($service->canRegisterProvider(CustomProviderService::STATE_REGISTERED, false));
        $this->assertFalse($service->canRegisterProvider(CustomProviderService::STATE_UNKNOWN, false));
    }

    public function test_target_location_is_registered_unless_already_connected(): void
    {
        $service = new CustomProviderService();

        $this->assertTrue($service->canRegisterProvider(CustomProviderService::STATE_MISSING, true));
        $this->assertTrue($service->canRegisterProvider(CustomProviderService::STATE_REGISTERED, true));
        $this->assertTrue($service->canRegisterProvider(CustomProviderService::STATE_UNKNOWN, true));
    }
}
