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
            $currency = strtoupper(trim((string) $request->input('currency', 'KWD')));
            $orderId = (string) ($request->input('orderId') ?? '');
            $transactionId = (string) ($request->input('transactionId') ?? '');
            $locationId = (string) ($request->input('locationId') ?? '');

            if ($amount <= 0 || $currency === '' || $locationId === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required fields: amount, currency, locationId',
                ], 400);
            }

            // UPayments expects a 3-letter ISO currency (e.g. KWD). GHL sometimes sends lowercase.
            if (!preg_match('/^[A-Z]{3}$/', $currency)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid currency. Expected 3-letter ISO code (e.g. KWD).',
                    'currency' => $currency,
                ], 400);
            }

            $user = User::where('lead_location_id', $locationId)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for locationId',
                ], 404);
            }

            // Use the mode the merchant chose during provider connection as the
            // source of truth.  GHL may send liveMode=false even when the provider
            // was connected in live mode, so the stored preference takes priority.
            $mode = $user->upayments_mode ?: ($request->has('liveMode')
                ? ($request->boolean('liveMode') ? 'live' : 'test')
                : 'test');
            $token = $mode === 'live'
                ? ($user->upayments_live_api_key ?? $user->upayments_live_token ?? null)
                : ($user->upayments_test_token ?? null);

            if ($mode === 'live' && empty($user->upayments_live_merchant_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'UPayments Merchant ID not configured for live mode',
                ], 400);
            }

            if (empty($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'UPayments token not configured for ' . $mode . ' mode',
                ], 400);
            }

            $baseUrl = $mode === 'live'
                ? config('services.upayments.live_base_url', 'https://apiv2api.upayments.com/api/v1/')
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

            // Derive the public base URL from the incoming request so we don't
            // depend on APP_URL being set correctly on every environment.
            $appBaseUrl = rtrim($request->getSchemeAndHttpHost(), '/');

            $returnUrl = $appBaseUrl . '/upayment/redirect'
                . '?locationId=' . urlencode($locationId)
                . '&transactionId=' . urlencode($finalTransactionId)
                . '&orderId=' . urlencode($finalOrderId);
            $cancelUrl = $returnUrl . '&cancel=true';
            $notificationUrl = $appBaseUrl . '/api/upayment/webhook';

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
                'stored_upayments_mode' => $user->upayments_mode,
                'request_liveMode' => $request->has('liveMode') ? (bool)$request->boolean('liveMode') : null,
                'endpoint' => $endpoint,
                'returnUrl' => $returnUrl,
                'notificationUrl' => $notificationUrl,
                'locationId' => $locationId,
                'amount' => $amount,
                'currency' => $currency,
                'orderId' => $finalOrderId,
                'transactionId' => $finalTransactionId,
                'has_live_merchant_id' => !empty($user->upayments_live_merchant_id),
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
     * Processes payment result and forwards payment.captured event to GHL.
     */
    public function webhook(Request $request)
    {
        $allData = $request->all();

        Log::info('🟣 [UPAYMENTS] Webhook received', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'all_data' => $allData,
            'headers' => $request->headers->all(),
            'raw_body' => $request->getContent(),
        ]);

        try {
            // UPayments nests data under data.transaction.*
            // e.g. { "data": { "transaction": { "track_id": "...", "result": "CAPTURED", ... } } }
            $trackId = (string) data_get($allData, 'data.transaction.track_id',
                data_get($allData, 'track_id',
                    data_get($allData, 'trackId',
                        data_get($allData, 'data.trackId', '')
                    )
                )
            );

            $result = strtoupper((string) data_get($allData, 'data.transaction.result',
                data_get($allData, 'result',
                    data_get($allData, 'data.result',
                        data_get($allData, 'data.transaction.status',
                            data_get($allData, 'payment_status',
                                data_get($allData, 'data.payment_status', '')
                            )
                        )
                    )
                )
            ));

            // "reference" in UPayments is where we stored the GHL transactionId during charge creation
            $orderRef = (string) data_get($allData, 'data.transaction.reference',
                data_get($allData, 'order.reference',
                    data_get($allData, 'data.order.reference',
                        data_get($allData, 'reference', '')
                    )
                )
            );

            $orderId = (string) data_get($allData, 'data.transaction.order_id',
                data_get($allData, 'order.id',
                    data_get($allData, 'data.order.id',
                        data_get($allData, 'order_id', '')
                    )
                )
            );

            $amount = (float) data_get($allData, 'data.transaction.total_price',
                data_get($allData, 'data.transaction.total_paid_non_kwd',
                    data_get($allData, 'order.amount',
                        data_get($allData, 'data.order.amount',
                            data_get($allData, 'amount',
                                data_get($allData, 'data.amount', 0)
                            )
                        )
                    )
                )
            );

            // orderRef is the GHL transactionId we stored during charge creation.
            $transactionId = $orderRef !== '' ? $orderRef : $orderId;

            Log::info('🟣 [UPAYMENTS] Webhook parsed', [
                'trackId' => $trackId,
                'result' => $result,
                'transactionId' => $transactionId,
                'orderId' => $orderId,
                'amount' => $amount,
            ]);

            if ($trackId === '' || $transactionId === '') {
                Log::warning('🟣 [UPAYMENTS] Webhook missing trackId or transactionId, skipping GHL notification');
                return response()->json(['status' => true]);
            }

            // Map UPayments result to GHL status
            $ghlStatus = 'pending';
            $failedResults = ['FAILED', 'FAIL', 'CANCELLED', 'CANCELED', 'DECLINED', 'ERROR', 'REVERSED', 'NOT CAPTURED', 'NOT_CAPTURED'];
            $successResults = ['CAPTURED', 'SUCCESS', 'SUCCEEDED', 'PAID', 'APPROVED', 'COMPLETED', 'AUTHORIZED', 'DONE'];

            if (in_array($result, $failedResults, true) || str_contains($result, 'NOT CAPTURE') || str_contains($result, 'NOT_CAPTURE')) {
                $ghlStatus = 'failed';
            } elseif (in_array($result, $successResults, true)) {
                $ghlStatus = 'succeeded';
            } elseif (str_contains($result, 'CANCEL') || str_contains($result, 'FAIL') || str_contains($result, 'DECLIN') || str_contains($result, 'NOT ')) {
                $ghlStatus = 'failed';
            } elseif (str_contains($result, 'CAPTURE') || str_contains($result, 'SUCCESS') || str_contains($result, 'PAID') || str_contains($result, 'DONE')) {
                $ghlStatus = 'succeeded';
            }

            if ($ghlStatus === 'pending') {
                Log::info('🟣 [UPAYMENTS] Webhook: payment still pending, not sending GHL event yet', [
                    'result' => $result,
                ]);
                return response()->json(['status' => true]);
            }

            // Find the user by looking up locationId via the charge metadata.
            // The webhook doesn't include locationId directly, so we search by order references.
            $user = null;
            $mode = 'test';

            // Try to extract customerExtraData or other location hints
            $customerExtra = (string) data_get($allData, 'data.transaction.customer_extra_data',
                data_get($allData, 'customerExtraData',
                    data_get($allData, 'data.customerExtraData', '')
                )
            );

            // Search for user across all locations (the transactionId should be unique)
            $users = User::whereNotNull('upayments_mode')->get();
            foreach ($users as $candidate) {
                $candidateMode = $candidate->upayments_mode ?: 'test';
                $candidateToken = $candidateMode === 'live'
                    ? ($candidate->upayments_live_api_key ?? $candidate->upayments_live_token ?? null)
                    : ($candidate->upayments_test_token ?? null);

                if (empty($candidateToken)) {
                    continue;
                }

                $baseUrl = $candidateMode === 'live'
                    ? config('services.upayments.live_base_url', 'https://apiv2api.upayments.com/api/v1/')
                    : config('services.upayments.test_base_url', 'https://sandboxapi.upayments.com/api/v1/');
                $baseUrl = rtrim($baseUrl, '/') . '/';
                $statusEndpoint = $baseUrl . 'get-payment-status/' . urlencode($trackId);

                try {
                    $resp = Http::timeout(10)
                        ->acceptJson()
                        ->withToken($candidateToken)
                        ->get($statusEndpoint);

                    if ($resp->successful()) {
                        $user = $candidate;
                        $mode = $candidateMode;
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            if (!$user) {
                Log::warning('🟣 [UPAYMENTS] Webhook: could not find matching user for trackId', [
                    'trackId' => $trackId,
                ]);
                return response()->json(['status' => true]);
            }

            Log::info('🟣 [UPAYMENTS] Webhook: sending payment.captured to GHL', [
                'trackId' => $trackId,
                'transactionId' => $transactionId,
                'ghlStatus' => $ghlStatus,
                'locationId' => $user->lead_location_id,
                'mode' => $mode,
            ]);

            $webhookService = new \App\Services\WebhookService();
            $webhookService->sendUPaymentsPaymentWebhook(
                $user,
                $trackId,
                $transactionId,
                $ghlStatus,
                $amount,
                $mode
            );
        } catch (\Exception $e) {
            Log::error('🟣 [UPAYMENTS] Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['status' => true]);
    }
}

