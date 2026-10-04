<?php

namespace Tests\Unit;

use App\Services\UPaymentsClient;
use Tests\TestCase;

class UPaymentsHmacTest extends TestCase
{
    public function test_signature_matches_upayments_payload_format(): void
    {
        $client = new UPaymentsClient();
        $secret = 'sk_test_01a0a4b450b4c4738b586d7f33134d44';
        $body = '{"amount":100,"currency":"USD","order_id":"ORD123"}';

        $signed = $client->sign('POST', 'charge', $body, $secret, 1718365200);

        $expected = base64_encode(hash_hmac(
            'sha256',
            '1718365200POSTcharge' . $body,
            $secret,
            true
        ));

        $this->assertSame('1718365200', $signed['timestamp']);
        $this->assertSame($expected, $signed['signature']);
    }

    public function test_get_requests_sign_an_empty_body(): void
    {
        $client = new UPaymentsClient();
        $secret = 'sk_test_01a0a4b450b4c4738b586d7f33134d44';
        $path = 'get-payment-status/track123';

        $signed = $client->sign('get', $path, '', $secret, 1718365200);

        $expected = base64_encode(hash_hmac(
            'sha256',
            '1718365200GET' . $path,
            $secret,
            true
        ));

        $this->assertSame($expected, $signed['signature']);
    }
}
