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
                case 'charge_payment':
                    return $this->handleChargePayment($request, $user);
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
        $apiKey = $user->lead_live_api_key ;
        
        $isLive = !empty($user->lead_live_api_key);
        
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
        $token = $request->input('token');
        
        if (!$amount || (!$paymentMethodId && !$token)) {
            return response()->json([
                'success' => false,
                'message' => 'Amount and payment method ID or token are required'
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
        
        // Create charge using token or payment method
        if ($token) {
            $charge = $tapService->createCharge($token, $amount, $currency);
        } else {
            $charge = $tapService->createChargeWithPaymentMethod($paymentMethodId, $amount, $currency);
        }

        if (!$charge) {
            return response()->json([
                'success' => false,
                'message' => 'Charge creation failed'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'chargeId' => $charge['id'],
            'amount' => $amount,
            'currency' => $currency,
            'status' => strtolower($charge['status'])
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
        $contactId = $request->input('contactId');
        
        if (!$contactId) {
            return response()->json([
                'success' => false,
                'message' => 'Contact ID is required'
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

        // TODO: Get customer ID from contact ID mapping
        // For now, we'll need to implement customer ID mapping
        $customerId = 'cus_' . $contactId; // This should be mapped from your database
        
        $tapService = new TapPaymentService($apiKey, '', $isLive);
        $paymentMethods = $tapService->getCustomerPaymentMethods($customerId);

        if (!$paymentMethods) {
            return response()->json([
                'success' => true,
                'paymentMethods' => []
            ]);
        }

        // Transform Tap response to GoHighLevel format
        $formattedMethods = [];
        if (isset($paymentMethods['data'])) {
            foreach ($paymentMethods['data'] as $method) {
                $formattedMethods[] = [
                    'id' => $method['id'],
                    'type' => 'card',
                    'title' => ucfirst(strtolower($method['brand'] ?? 'Card')),
                    'subTitle' => 'XXXX-' . ($method['last_four'] ?? '0000'),
                    'expiry' => ($method['exp_month'] ?? '01') . '/' . ($method['exp_year'] ?? '25'),
                    'customerId' => $customerId,
                    'imageUrl' => $this->getCardImageUrl($method['brand'] ?? 'VISA')
                ];
            }
        }

        return response()->json([
            'success' => true,
            'paymentMethods' => $formattedMethods
        ]);
    }

    /**
     * Get card image URL based on brand
     */
    private function getCardImageUrl($brand)
    {
        $brandImages = [
            'VISA' => 'https://upload.wikimedia.org/wikipedia/commons/4/41/Visa_Logo.png',
            'MASTERCARD' => 'https://upload.wikimedia.org/wikipedia/commons/2/2a/Mastercard-logo.svg',
            'AMERICAN_EXPRESS' => 'https://upload.wikimedia.org/wikipedia/commons/3/30/American_Express_logo.svg',
            'MADA' => 'https://mada.com.sa/wp-content/themes/mada/assets/images/logo.svg'
        ];

        return $brandImages[strtoupper($brand)] ?? $brandImages['VISA'];
    }

    /**
     * Handle charge payment method
     */
    private function handleChargePayment(Request $request, User $user)
    {
        $paymentMethodId = $request->input('paymentMethodId');
        $amount = $request->input('amount');
        $currency = $request->input('currency', 'JOD');
        $transactionId = $request->input('transactionId');
        $chargeDescription = $request->input('chargeDescription', 'Payment');
        $contactId = $request->input('contactId');
        
        if (!$paymentMethodId || !$amount || !$transactionId) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method ID, amount, and transaction ID are required'
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
        
        // Get customer ID from contact ID
        $customerId = 'cus_' . $contactId; // This should be mapped from your database
        
        $charge = $tapService->createChargeWithPaymentMethod(
            $paymentMethodId, 
            $amount, 
            $currency, 
            $customerId, 
            $chargeDescription
        );

        if (!$charge) {
            return response()->json([
                'success' => false,
                'failed' => true,
                'message' => 'Payment failed'
            ], 500);
        }

        $isSuccessful = strtolower($charge['status']) === 'captured' || strtolower($charge['status']) === 'succeeded';
        
        return response()->json([
            'success' => $isSuccessful,
            'failed' => !$isSuccessful,
            'chargeId' => $charge['id'],
            'message' => $isSuccessful ? 'Payment successful' : 'Payment failed',
            'chargeSnapshot' => [
                'id' => $charge['id'],
                'status' => strtolower($charge['status']),
                'amount' => $charge['amount'],
                'chargeId' => $charge['id'],
                'chargedAt' => strtotime($charge['created'])
            ]
        ]);
    }
}
