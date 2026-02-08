<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UPaymentsStatusController extends Controller
{
    public function status(Request $request)
    {
        try {
            $trackId = (string) ($request->input('track_id') ?? $request->input('trackId') ?? '');
            $locationId = (string) ($request->input('locationId') ?? '');

            if ($trackId === '' || $locationId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'track_id and locationId are required',
                ], 400);
            }

            $user = User::where('lead_location_id', $locationId)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for locationId',
                ], 404);
            }

            $mode = $user->upayments_mode ?: 'test';
            $token = $mode === 'live' ? ($user->upayments_live_token ?? null) : ($user->upayments_test_token ?? null);

            // If mode token is missing, try the other one as fallback.
            if (empty($token)) {
                $token = $mode === 'live' ? ($user->upayments_test_token ?? null) : ($user->upayments_live_token ?? null);
            }

            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'UPayments token not configured',
                ], 400);
            }

            $baseUrl = $mode === 'live'
                ? config('services.upayments.live_base_url', 'https://api.upayments.com/api/v1/')
                : config('services.upayments.test_base_url', 'https://sandboxapi.upayments.com/api/v1/');
            $baseUrl = rtrim($baseUrl, '/') . '/';

            $endpoint = $baseUrl . 'get-payment-status/' . urlencode($trackId);

            Log::info('🟣 [UPAYMENTS] Fetching payment status', [
                'mode' => $mode,
                'endpoint' => $endpoint,
                'locationId' => $locationId,
                'trackId' => $trackId,
                'token_prefix' => substr((string)$token, 0, 8) . '...',
            ]);

            $resp = Http::timeout(20)
                ->acceptJson()
                ->withToken($token)
                ->get($endpoint);

            if ($resp->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'UPayments status API failed',
                    'status_code' => $resp->status(),
                    'error' => $resp->json() ?: $resp->body(),
                ], 502);
            }

            $json = $resp->json() ?? [];
            $rawResult = $this->extractResult($json);
            $mapped = $this->mapResultToState($rawResult);

            return response()->json([
                'success' => true,
                'trackId' => $trackId,
                'result' => $rawResult,
                'state' => $mapped['state'], // succeeded|failed|pending
                'is_successful' => $mapped['is_successful'],
                'is_failed' => $mapped['is_failed'],
                'raw' => $json,
            ]);
        } catch (\Exception $e) {
            Log::error('🟣 [UPAYMENTS] Status exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Status check failed',
            ], 500);
        }
    }

    private function extractResult(array $json): string
    {
        $candidates = [
            data_get($json, 'data.result'),
            data_get($json, 'data.payment_status'),
            data_get($json, 'data.paymentStatus'),
            data_get($json, 'result'),
            data_get($json, 'status'),
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

        $successStates = ['CAPTURED', 'SUCCESS', 'SUCCEEDED', 'PAID', 'APPROVED', 'COMPLETED', 'AUTHORIZED'];
        $failedStates = ['FAILED', 'FAIL', 'CANCELLED', 'CANCELED', 'DECLINED', 'ERROR', 'REVERSED'];
        $pendingStates = ['PENDING', 'INITIATED', 'PROCESSING'];

        if (in_array($result, $successStates, true)) {
            return ['state' => 'succeeded', 'is_successful' => true, 'is_failed' => false];
        }
        if (in_array($result, $failedStates, true)) {
            return ['state' => 'failed', 'is_successful' => false, 'is_failed' => true];
        }
        if (in_array($result, $pendingStates, true)) {
            return ['state' => 'pending', 'is_successful' => false, 'is_failed' => false];
        }

        // Heuristic fallback
        if (str_contains($result, 'CAPTURE') || str_contains($result, 'SUCCESS') || str_contains($result, 'PAID')) {
            return ['state' => 'succeeded', 'is_successful' => true, 'is_failed' => false];
        }
        if (str_contains($result, 'CANCEL') || str_contains($result, 'FAIL') || str_contains($result, 'DECLIN')) {
            return ['state' => 'failed', 'is_successful' => false, 'is_failed' => true];
        }

        return ['state' => 'pending', 'is_successful' => false, 'is_failed' => false];
    }
}

