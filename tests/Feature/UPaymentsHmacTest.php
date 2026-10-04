<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UPaymentsHmacTest extends TestCase
{
    use RefreshDatabase;

    private const LOCATION_ID = 'loc_upayments_hmac';
    private const API_KEY = 'NWty8gdjt79cf756hdtyu6u8jht5ns6gfhgy8hdjL';
    private const API_SECRET = 'sk_test_01a0a4b450b4c4738b586d7f33134d44';

    private function merchant(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'UPayments Merchant',
            'email' => 'upayments-hmac@example.com',
            'password' => Hash::make('secret'),
            'lead_location_id' => self::LOCATION_ID,
            'upayments_mode' => 'test',
            'upayments_test_token' => self::API_KEY,
            'upayments_test_api_secret' => self::API_SECRET,
            'upayments_lead_access_token' => 'ghl-token',
            'upayments_lead_token_expires_at' => now()->addDay(),
            'upayments_lead_user_type' => 'Location',
        ], $overrides));
    }

    public function test_charge_request_is_signed_with_the_exact_json_body(): void
    {
        $this->merchant();

        Http::fake([
            'sandboxapi.upayments.com/*' => Http::response([
                'status' => true,
                'data' => [
                    'link' => 'https://sandbox.upayments.com/pay/abc',
                    'trackId' => 'track123',
                ],
            ], 201),
        ]);

        $this->postJson('/api/charge/create-upayment', [
            'amount' => 10,
            'currency' => 'KWD',
            'locationId' => self::LOCATION_ID,
            'orderId' => 'ord_1',
            'transactionId' => 'txn_1',
        ])->assertOk()->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            $body = $request->body();
            $timestamp = $request->header('X-Timestamp')[0] ?? '';
            $signature = $request->header('X-Signature')[0] ?? '';
            $expected = base64_encode(hash_hmac(
                'sha256',
                $timestamp . 'POST' . 'charge' . $body,
                self::API_SECRET,
                true
            ));

            return $request->method() === 'POST'
                && str_ends_with($request->url(), '/api/v1/charge')
                && $request->hasHeader('Authorization', 'Bearer ' . self::API_KEY)
                && $signature === $expected
                && str_contains($body, '"currency":"KWD"');
        });
    }

    public function test_status_request_signs_the_path_with_an_empty_body(): void
    {
        $this->merchant();

        Http::fake([
            'sandboxapi.upayments.com/*' => Http::response([
                'status' => true,
                'data' => [
                    'transaction' => [
                        'result' => 'CAPTURED',
                    ],
                ],
            ], 200),
        ]);

        $this->getJson('/api/upayment/status?track_id=track123&locationId=' . self::LOCATION_ID)
            ->assertOk()
            ->assertJsonPath('state', 'succeeded');

        Http::assertSent(function ($request) {
            $timestamp = $request->header('X-Timestamp')[0] ?? '';
            $signature = $request->header('X-Signature')[0] ?? '';
            $path = 'get-payment-status/track123';
            $expected = base64_encode(hash_hmac(
                'sha256',
                $timestamp . 'GET' . $path,
                self::API_SECRET,
                true
            ));

            return $request->method() === 'GET'
                && str_contains($request->url(), '/api/v1/' . $path)
                && $signature === $expected
                && $request->body() === '';
        });
    }

    public function test_missing_secret_still_sends_the_charge_without_hmac_headers(): void
    {
        $this->merchant(['upayments_test_api_secret' => null]);

        Http::fake([
            'sandboxapi.upayments.com/*' => Http::response([
                'status' => true,
                'data' => [
                    'link' => 'https://sandbox.upayments.com/pay/abc',
                    'trackId' => 'track123',
                ],
            ], 201),
        ]);

        $this->postJson('/api/charge/create-upayment', [
            'amount' => 10,
            'currency' => 'KWD',
            'locationId' => self::LOCATION_ID,
            'orderId' => 'ord_1',
            'transactionId' => 'txn_1',
        ])->assertOk();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && ! $request->hasHeader('X-Signature')
                && ! $request->hasHeader('X-Timestamp');
        });
    }

    public function test_connect_requires_api_secret_until_one_is_saved(): void
    {
        $this->merchant(['upayments_test_api_secret' => null]);

        Http::fake();

        $state = base64_encode(json_encode(['type' => 'location', 'id' => self::LOCATION_ID]));

        $this->post('/uprovider/connect-or-disconnect', [
            'action' => 'connect',
            'information' => 'https://app.gohighlevel.com/integration?state=' . $state,
            'upayments_mode' => 'test',
            'upayments_test_token' => self::API_KEY,
        ])->assertRedirect()->assertSessionHas('api_error');

        Http::assertNothingSent();
    }

    public function test_connect_saves_the_api_secret(): void
    {
        $user = $this->merchant(['upayments_test_api_secret' => null]);

        Http::fake([
            'services.leadconnectorhq.com/*' => Http::response(['ok' => true], 200),
        ]);

        $state = base64_encode(json_encode(['type' => 'location', 'id' => self::LOCATION_ID]));

        $this->post('/uprovider/connect-or-disconnect', [
            'action' => 'connect',
            'information' => 'https://app.gohighlevel.com/integration?state=' . $state,
            'upayments_mode' => 'test',
            'upayments_test_token' => self::API_KEY,
            'upayments_test_api_secret' => self::API_SECRET,
        ])->assertRedirect()->assertSessionHas('success', true);

        $this->assertSame(self::API_SECRET, $user->fresh()->upayments_test_api_secret);
    }

    public function test_landing_page_asks_for_the_api_secret(): void
    {
        $response = $this->get('/Ulanding');

        $response->assertOk();
        $response->assertSee('name="upayments_test_api_secret"', false);
        $response->assertSee('name="upayments_live_api_secret"', false);
        $response->assertSee('31 December 2026');
    }
}
