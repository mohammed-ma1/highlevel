<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Services\TapPaymentService;

class PaymentQueryController extends Controller
{
    /**
     * Handle payment queries from GoHighLevel
     * This endpoint receives requests for payment processing, verification, refunds, etc.
     */
    public function handleQuery(Request $request)
    {
        try {
            // Extract required parameters
            $type = $request->input('type');
            $locationId = $request->input('locationId');
            $apiKey = $request->input('apiKey');
            
            Log::info('Payment query received', [
                'type' => $type,
                'locationId' => $locationId,
                'request_data' => $request->all()
            ]);

            // Validate required parameters
            if (!$type || !$locationId || !$apiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing required parameters: type, locationId, apiKey'
                ], 400);
            }

            // Find user by location ID
            $user = User::where('lead_location_id', $locationId)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Location not found'
                ], 404);
            }

            // Route to appropriate handler based on type
            switch ($type) {
                case 'verify':
                    return $this->handleVerify($request, $user);
                case 'refund':
                    return $this->handleRefund($request, $user);
                case 'charge':
                    return $this->handleCharge($request, $user);
                case 'create_subscription':
                    return $this->handleCreateSubscription($request, $user);
                case 'update_subscription':
                    return $this->handleUpdateSubscription($request, $user);
                case 'cancel_subscription':
                    return $this->handleCancelSubscription($request, $user);
                case 'list_payment_methods':
                    return $this->handleListPaymentMethods($request, $user);
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unsupported payment type: ' . $type
                    ], 400);
            }

        } catch (\Exception $e) {
            Log::error('Payment query error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Handle payment verification
     */
    private function handleVerify(Request $request, User $user)
    {
        $transactionId = $request->input('transactionId');
        
        if (!$transactionId) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction ID is required'
            ], 400);
        }

        // Get user's Tap credentials
        $apiKey = $user->lead_live_api_key ?? $user->lead_test_api_key;
        $isLive = !empty($user->lead_live_api_key);
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'No API key configured for this location'
            ], 400);
        }

        $tapService = new TapPaymentService($apiKey, '', $isLive);
        $charge = $tapService->retrieveCharge($transactionId);

        if (!$charge) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'verified' => $charge['status'] === 'CAPTURED',
            'transactionId' => $transactionId,
            'status' => strtolower($charge['status']),
            'amount' => $charge['amount'],
            'currency' => $charge['currency']
        ]);
    }

    /**
     * Handle payment refund
     */
    private function handleRefund(Request $request, User $user)
    {
        $transactionId = $request->input('transactionId');
        $amount = $request->input('amount');
        
        if (!$transactionId || !$amount) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction ID and amount are required'
            ], 400);
        }

        // Get user's Tap credentials
        $apiKey = $user->lead_live_api_key ?? '5tap61';
        
        $isLive = !empty($user->lead_live_api_key) ?? false;
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'No API key configured for this location'
            ], 400);
        }

        $tapService = new TapPaymentService($apiKey, '', $isLive);
        $refund = $tapService->createRefund($transactionId, $amount);

        if (!$refund) {
            return response()->json([
                'success' => false,
                'message' => 'Refund failed'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'refundId' => $refund['id'],
            'amount' => $amount,
            'status' => strtolower($refund['status'])
        ]);
    }

    /**
     * Handle payment charge
     */
    private function handleCharge(Request $request, User $user)
    {
        $amount = $request->input('amount');
        $currency = $request->input('currency', 'JOD');
        $paymentMethodId = $request->input('payment_method_id');
        
        if (!$amount || !$paymentMethodId) {
            return response()->json([
                'success' => false,
                'message' => 'Amount and payment method ID are required'
            ], 400);
        }

        // TODO: Implement actual charge with Tap API
        // For now, return a mock response
        return response()->json([
            'success' => true,
            'chargeId' => 'chg_' . uniqid(),
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'succeeded'
        ]);
    }

    /**
     * Handle subscription creation
     */
    private function handleCreateSubscription(Request $request, User $user)
    {
        $subscriptionData = $request->all();
        
        // TODO: Implement actual subscription creation with Tap API
        // For now, return a mock response
        return response()->json([
            'success' => true,
            'failed' => false,
            'message' => 'Subscription created',
            'subscription' => [
                'subscriptionId' => 'sub_' . uniqid(),
                'subscriptionSnapshot' => [
                    'id' => 'sub_' . uniqid(),
                    'status' => 'active',
                    'trialEnd' => 0,
                    'createdAt' => time(),
                    'nextCharge' => time() + (30 * 24 * 60 * 60) // 30 days from now
                ]
            ]
        ]);
    }

    /**
     * Handle subscription update
     */
    private function handleUpdateSubscription(Request $request, User $user)
    {
        $subscriptionId = $request->input('subscriptionId');
        
        if (!$subscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID is required'
            ], 400);
        }

        // TODO: Implement actual subscription update with Tap API
        return response()->json([
            'success' => true,
            'message' => 'Subscription updated'
        ]);
    }

    /**
     * Handle subscription cancellation
     */
    private function handleCancelSubscription(Request $request, User $user)
    {
        $subscriptionId = $request->input('subscriptionId');
        
        if (!$subscriptionId) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription ID is required'
            ], 400);
        }

        // TODO: Implement actual subscription cancellation with Tap API
        return response()->json([
            'success' => true,
            'message' => 'Subscription cancelled'
        ]);
    }

    /**
     * Handle payment methods listing
     */
    private function handleListPaymentMethods(Request $request, User $user)
    {
        // TODO: Implement actual payment methods listing with Tap API
        // For now, return a mock response
        return response()->json([
            'success' => true,
            'paymentMethods' => [
                [
                    'id' => 'pm_' . uniqid(),
                    'type' => 'card',
                    'last4' => '4242',
                    'brand' => 'visa',
                    'exp_month' => 12,
                    'exp_year' => 2025
                ]
            ]
        ]);
    }
}
