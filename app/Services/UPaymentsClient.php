<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Signed HTTP client for the UPayments gateway.
 *
 * https://developers.upayments.com/reference/hmac-authentication
 *
 * payload = timestamp + HTTP_METHOD + path_after_/api/v1/ + raw_body
 * X-Signature = Base64(HMAC_SHA256(payload, API_SECRET))
 * X-Timestamp = Unix time in UTC seconds
 */
class UPaymentsClient
{
    public function credentials(User $user, string $mode): array
    {
        if ($mode === 'live') {
            $token = trim((string) ($user->upayments_live_api_key ?? $user->upayments_live_token ?? ''));
            $secret = trim((string) ($user->upayments_live_api_secret ?? ''));
        } else {
            $token = trim((string) ($user->upayments_test_token ?? ''));
            $secret = trim((string) ($user->upayments_test_api_secret ?? ''));
        }

        return [
            'token' => $token !== '' ? $token : null,
            'secret' => $secret !== '' ? $secret : null,
        ];
    }

    public function baseUrl(string $mode): string
    {
        $base = $mode === 'live'
            ? config('services.upayments.live_base_url', 'https://apiv2api.upayments.com/api/v1/')
            : config('services.upayments.test_base_url', 'https://sandboxapi.upayments.com/api/v1/');

        return rtrim((string) $base, '/') . '/';
    }

    /**
     * @return array{timestamp: string, signature: string}
     */
    public function sign(string $method, string $path, string $body, string $apiSecret, ?int $timestamp = null): array
    {
        $timestamp = $timestamp ?? time();
        $payload = $timestamp . strtoupper($method) . ltrim($path, '/') . $body;

        return [
            'timestamp' => (string) $timestamp,
            'signature' => base64_encode(hash_hmac('sha256', $payload, $apiSecret, true)),
        ];
    }

    public function encodeBody(?array $json): string
    {
        if ($json === null) {
            return '';
        }

        $encoded = json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode UPayments request body');
        }

        return $encoded;
    }

    public function request(
        string $method,
        string $mode,
        string $token,
        ?string $apiSecret,
        string $path,
        ?array $json = null,
        int $timeout = 30
    ): Response {
        $method = strtoupper($method);
        $path = ltrim($path, '/');
        $body = $method === 'GET' ? '' : $this->encodeBody($json ?? []);
        $url = $this->baseUrl($mode) . $path;

        $headers = [];
        if ($apiSecret !== null && $apiSecret !== '') {
            $signed = $this->sign($method, $path, $body, $apiSecret);
            $headers['X-Timestamp'] = $signed['timestamp'];
            $headers['X-Signature'] = $signed['signature'];
        } else {
            Log::warning('🟣 [UPAYMENTS] API secret missing; request sent without HMAC', [
                'mode' => $mode,
                'method' => $method,
                'path' => $path,
            ]);
        }

        $pending = Http::timeout($timeout)
            ->acceptJson()
            ->withToken($token)
            ->withHeaders($headers);

        if ($method === 'GET') {
            return $pending->get($url);
        }

        return $pending->withBody($body, 'application/json')->send($method, $url);
    }
}
