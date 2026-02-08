<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UPaymentsChargeController extends Controller
{
    /**
     * Create a UPayments (Non-Whitelabel) charge and return hosted checkout link.
     */
    public function createCharge(Request $request)
    {
        try {
            $amount = (float) $request->input('amount');
            $currency = (string) $request->input('currency', 'KWD');
            $orderId = (string) ($request->input('orderId') ?? '');
            $transactionId = (string) ($request->input('transactionId') ?? '');
            $locationId = (string) ($request->input('locationId') ?? '');

            if ($amount <= 0 || $currency === '' || $locationId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields: amount, currency, locationId',
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

            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'UPayments token not configured for ' . $mode . ' mode',
                ], 400);
            }

            $baseUrl = $mode === 'live'
                ? config('services.upayments.live_base_url', 'https://api.upayments.com/api/v1/')
                : config('services.upayments.test_base_url', 'https://sandboxapi.upayments.com/api/v1/');

            $baseUrl = rtrim($baseUrl, '/') . '/';
            $endpoint = $baseUrl . 'charge';

            $contact = $request->input('contact') ?: $request->input('customer');
            $customerUniqueId = (string) data_get($contact, 'id', '');
            if ($customerUniqueId === '') {
                $customerUniqueId = 'loc_' . $locationId;
            }
            $customerName = (string) data_get($contact, 'name', 'Customer');
            $customerEmail = (string) data_get($contact, 'email', 'customer@example.com');
            $customerMobile = (string) data_get($contact, 'contact', data_get($contact, 'mobile', '+96500000000'));
            if ($customerMobile !== '' && $customerMobile[0] !== '+') {
                $customerMobile = '+' . preg_replace('/\s+/', '', $customerMobile);
            }

            $finalOrderId = $orderId !== '' ? $orderId : ('ord_' . Str::uuid()->toString());
            $finalTransactionId = $transactionId !== '' ? $transactionId : ('txn_' . Str::uuid()->toString());

            $returnUrl = rtrim(config('app.url'), '/') . '/upayment/redirect'
                . '?locationId=' . urlencode($locationId)
                . '&transactionId=' . urlencode($finalTransactionId)
                . '&orderId=' . urlencode($finalOrderId);
            $cancelUrl = $returnUrl . '&cancel=true';
            $notificationUrl = rtrim(config('app.url'), '/') . '/api/upayment/webhook';

            // Build Non-Whitelabel request model (hosted checkout).
            $payload = [
                'products' => $request->input('products') ?: [
                    [
                        'name' => 'Order ' . $finalOrderId,
                        'description' => (string) ($request->input('description') ?? 'Payment via GoHighLevel Integration'),
                        'price' => $amount,
                        'quantity' => 1,
                    ],
                ],
                'order' => [
                    'id' => $finalOrderId,
                    'reference' => $finalTransactionId,
                    'description' => (string) ($request->input('description') ?? ('Payment for order ' . $finalOrderId)),
                    'currency' => $currency,
                    'amount' => $amount,
                ],
                'language' => (string) $request->input('language', 'en'),
                'reference' => [
                    'id' => (string) ($request->input('referenceId') ?? $finalOrderId),
                ],
                'customer' => [
                    'uniqueId' => $customerUniqueId,
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'mobile' => $customerMobile,
                ],
                'returnUrl' => $returnUrl,
                'cancelUrl' => $cancelUrl,
                'notificationUrl' => $notificationUrl,
                'customerExtraData' => (string) ($request->input('customerExtraData') ?? ''),
            ];

            Log::info('🟣 [UPAYMENTS] Creating charge', [
                'mode' => $mode,
                'endpoint' => $endpoint,
                'locationId' => $locationId,
                'amount' => $amount,
                'currency' => $currency,
                'orderId' => $finalOrderId,
                'transactionId' => $finalTransactionId,
                'token_prefix' => substr((string)$token, 0, 8) . '...',
            ]);

            $resp = Http::timeout(30)
                ->acceptJson()
                ->withToken($token)
                ->post($endpoint, $payload);

            if ($resp->failed()) {
                Log::error('🟣 [UPAYMENTS] Charge API failed', [
                    'status' => $resp->status(),
                    'body' => $resp->json() ?: $resp->body(),
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'UPayments charge API failed',
                    'error' => $resp->json() ?: $resp->body(),
                ], 502);
            }

            $data = $resp->json() ?? [];
            $link = data_get($data, 'data.link');
            $trackId = data_get($data, 'data.trackId');

            if (!$link) {
                return response()->json([
                    'success' => false,
                    'message' => 'UPayments did not return a payment link',
                    'raw' => $data,
                ], 502);
            }

            return response()->json([
                'success' => true,
                'link' => $link,
                'trackId' => $trackId,
                'raw' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('🟣 [UPAYMENTS] Charge creation exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Charge creation failed',
            ], 500);
        }
    }

    /**
     * Webhook receiver for UPayments notificationUrl.
     * Saves nothing yet; just logs for debugging and returns HTTP 200.
     */
    public function webhook(Request $request)
    {
        Log::info('🟣 [UPAYMENTS] Webhook received', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'all_data' => $request->all(),
            'headers' => $request->headers->all(),
            'raw_body' => $request->getContent(),
        ]);

        return response()->json(['status' => true]);
    }
}

