<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UPaymentsQueryController extends Controller
{
    /**
     * GoHighLevel payment query endpoint for UPayments.
     *
     * Per HighLevel requirements, always return HTTP 200 and use:
     * - { "success": true } for success
     * - { "failed": true } for failure
     * - { "success": false } for pending/unknown
     */
    public function handleQuery(Request $request)
    {
        try {
            $type = (string) $request->input('type');
            $locationId = (string) ($request->input('locationId') ?? '');
            $apiKey = (string) ($request->input('apiKey') ?? '');

            Log::info('🟣 [UPAYMENTS] Payment query received', [
                'type' => $type,
                'locationId' => $locationId ?: null,
                'has_apiKey' => $apiKey !== '',
                'request_keys' => array_keys($request->all()),
            ]);

            if ($type === '' || $apiKey === '') {
                return response()->json(['success' => false], 200);
            }

            $user = null;
            if ($locationId !== '') {
                $user = User::where('lead_location_id', $locationId)->first();
            }

            if (!$user) {
                $user = User::where('upayments_test_token', $apiKey)
                    ->orWhere('upayments_live_api_key', $apiKey)
                    ->orWhere('upayments_live_token', $apiKey) // legacy
                    ->first();
            }

            if (!$user) {
                return response()->json(['success' => false], 200);
            }

            // Determine mode from apiKey (preferred), else from stored user mode.
            $mode = null;
            if (!empty($user->upayments_live_api_key) && $apiKey === $user->upayments_live_api_key) {
                $mode = 'live';
            } elseif (!empty($user->upayments_live_token) && $apiKey === $user->upayments_live_token) {
                $mode = 'live';
            } elseif (!empty($user->upayments_test_token) && $apiKey === $user->upayments_test_token) {
                $mode = 'test';
            } else {
                $mode = $user->upayments_mode ?: 'test';
            }

            $expectedKey = $mode === 'live'
                ? (($user->upayments_live_api_key ?? '') !== '' ? ($user->upayments_live_api_key ?? '') : ($user->upayments_live_token ?? ''))
                : ($user->upayments_test_token ?? '');
            if ($expectedKey === '' || $apiKey !== $expectedKey) {
                Log::warning('🟣 [UPAYMENTS] API key validation failed', [
                    'user_id' => $user->id,
                    'mode' => $mode,
                    'locationId' => $user->lead_location_id,
                ]);
                return response()->json(['failed' => true], 200);
            }

            switch ($type) {
                case 'verify':
                    return $this->handleVerify($request, $user, $mode);
                default:
                    // Not implemented yet
                    return response()->json(['success' => false], 200);
            }
        } catch (\Exception $e) {
            Log::error('🟣 [UPAYMENTS] Payment query error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['failed' => true], 200);
        }
    }

    private function handleVerify(Request $request, User $user, string $mode)
    {
        $transactionId = (string) ($request->input('transactionId') ?? '');
        $chargeId = (string) ($request->input('chargeId') ?? '');

        // We treat chargeId as trackId; if missing, fall back to transactionId.
        $trackId = $chargeId !== '' ? $chargeId : $transactionId;
        if ($trackId === '') {
            return response()->json(['failed' => true], 200);
        }

        $token = $mode === 'live'
            ? ($user->upayments_live_api_key ?? $user->upayments_live_token ?? null)
            : ($user->upayments_test_token ?? null);
        if (empty($token)) {
            return response()->json(['failed' => true], 200);
        }

        $baseUrl = $mode === 'live'
            ? config('services.upayments.live_base_url', 'https://apiv2api.upayments.com/api/v1/')
            : config('services.upayments.test_base_url', 'https://sandboxapi.upayments.com/api/v1/');
        $baseUrl = rtrim($baseUrl, '/') . '/';
        $endpoint = $baseUrl . 'get-payment-status/' . urlencode($trackId);

        try {
            $resp = Http::timeout(20)
                ->acceptJson()
                ->withToken($token)
                ->get($endpoint);

            if ($resp->failed()) {
                Log::warning('🟣 [UPAYMENTS] Verify: status API failed', [
                    'status' => $resp->status(),
                    'body' => $resp->json() ?: $resp->body(),
                    'trackId' => $trackId,
                    'mode' => $mode,
                ]);
                // Pending/unknown in GHL terms
                return response()->json(['success' => false], 200);
            }

            $json = $resp->json() ?? [];
            $result = $this->extractResult($json);
            $mapped = $this->mapResultToState($result);

            Log::info('🟣 [UPAYMENTS] Verify result', [
                'trackId' => $trackId,
                'transactionId' => $transactionId,
                'result' => $result,
                'state' => $mapped['state'],
                'mode' => $mode,
                'locationId' => $user->lead_location_id,
            ]);

            $isFinalState = in_array($mapped['state'], ['succeeded', 'failed'], true);
            if ($isFinalState) {
                // UPayments returns amount under data.transaction.total_price (string like "20.000")
                $amount = (float) data_get($json, 'data.transaction.total_price',
                    data_get($json, 'data.transaction.total_paid_non_kwd',
                        data_get($json, 'data.order.amount',
                            data_get($json, 'data.amount',
                                data_get($json, 'amount', 0)
                            )
                        )
                    )
                );

                $webhookService = new \App\Services\WebhookService();
                $webhookService->sendUPaymentsPaymentWebhook(
                    $user,
                    $trackId,
                    $transactionId,
                    $mapped['state'],
                    $amount,
                    $mode
                );
            }

            if ($mapped['state'] === 'succeeded') {
                return response()->json(['success' => true], 200);
            }
            if ($mapped['state'] === 'failed') {
                return response()->json(['failed' => true], 200);
            }

            return response()->json(['success' => false], 200);
        } catch (\Exception $e) {
            Log::warning('🟣 [UPAYMENTS] Verify: exception', [
                'error' => $e->getMessage(),
                'trackId' => $trackId,
                'mode' => $mode,
            ]);
            return response()->json(['success' => false], 200);
        }
    }

    private function extractResult(array $json): string
    {
        // UPayments nests the payment data under data.transaction
        // e.g. { "status": true, "data": { "transaction": { "result": "CAPTURED", "status": "done", ... } } }
        $candidates = [
            data_get($json, 'data.transaction.result'),
            data_get($json, 'data.transaction.status'),
            data_get($json, 'data.transaction.payment_status'),
            data_get($json, 'data.result'),
            data_get($json, 'data.payment_status'),
            data_get($json, 'data.paymentStatus'),
            data_get($json, 'result'),
            data_get($json, 'data.status'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return strtoupper(trim($value));
            }
        }

        return 'UNKNOWN';
    }

    private function mapResultToState(string $result): array
    {
        $result = strtoupper($result);

        // UPayments returns result="CAPTURED" for success, status="done" as secondary
        $successStates = ['CAPTURED', 'SUCCESS', 'SUCCEEDED', 'PAID', 'APPROVED', 'COMPLETED', 'AUTHORIZED', 'DONE'];
        $failedStates = ['FAILED', 'FAIL', 'CANCELLED', 'CANCELED', 'DECLINED', 'ERROR', 'REVERSED', 'NOT CAPTURED', 'NOT_CAPTURED'];
        $pendingStates = ['PENDING', 'INITIATED', 'PROCESSING'];

        if (in_array($result, $failedStates, true)) {
            return ['state' => 'failed'];
        }
        if (in_array($result, $successStates, true)) {
            return ['state' => 'succeeded'];
        }
        if (in_array($result, $pendingStates, true)) {
            return ['state' => 'pending'];
        }

        // Heuristic fallback — check negation patterns before positive ones
        if (str_contains($result, 'NOT CAPTURE') || str_contains($result, 'NOT_CAPTURE')) {
            return ['state' => 'failed'];
        }
        if (str_contains($result, 'CANCEL') || str_contains($result, 'FAIL') || str_contains($result, 'DECLIN') || str_contains($result, 'NOT ')) {
            return ['state' => 'failed'];
        }
        if (str_contains($result, 'CAPTURE') || str_contains($result, 'SUCCESS') || str_contains($result, 'PAID') || str_contains($result, 'DONE')) {
            return ['state' => 'succeeded'];
        }

        return ['state' => 'pending'];
    }
}

