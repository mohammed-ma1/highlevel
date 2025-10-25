<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\TapPaymentService;
use App\Models\User;

class GHLIntegrationController extends Controller
{
    /**
     * Main query handler - routes requests based on type
     */
    public function handleQuery(Request $request)
    {
        Log::info('GHL Query request received', [
            'data' => $request->all(),
            'url' => $request->fullUrl(),
            'method' => $request->method()
        ]);

        $type = $request->input('type');
        
        switch ($type) {
            case 'verify':
                return $this->verifyPayment($request);
            case 'refund':
                return $this->refundPayment($request);
            case 'list_payment_methods':
                return $this->listPaymentMethods($request);
            case 'charge_payment':
                return $this->chargePaymentMethod($request);
            case 'create_subscription':
                return $this->createSubscription($request);
            default:
                Log::warning('Unknown query type', ['type' => $type]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unknown query type: ' . $type
                ], 400);
        }
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(Request $request)
    {
        try {
            $chargeId = $request->input('chargeId');
            $transactionId = $request->input('transactionId');
            $locationId = $request->input('locationId');
            $apiKey = $request->input('apiKey');

            Log::info('Verifying payment', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'locationId' => $locationId
            ]);

            if (!$chargeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Charge ID is required'
                ], 400);
            }

            // Find user by location ID
            $user = User::where('lead_location_id', $locationId)->first();
            if (!$user) {
                Log::warning('User not found for location', ['locationId' => $locationId]);
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for location'
                ], 404);
            }

            // Get API keys from user
            $tapApiKey = $user->lead_test_api_key ?? $user->lead_live_api_key;
            if (!$tapApiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'API keys not configured'
                ], 400);
            }

            // Initialize Tap service
            $tapService = new TapPaymentService($tapApiKey, '');

            // Retrieve charge from Tap
            $charge = $tapService->retrieveCharge($chargeId);
            
            if (!$charge) {
                Log::error('Failed to retrieve charge from Tap', ['chargeId' => $chargeId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve charge information'
                ], 500);
            }

            Log::info('Tap charge status', [
                'chargeId' => $chargeId,
                'status' => $charge['status'] ?? 'unknown',
                'charge' => $charge
            ]);

            // Check charge status
            $status = $charge['status'] ?? 'UNKNOWN';
            
            switch ($status) {
                case 'CAPTURED':
                    Log::info('Payment verified as successful', ['chargeId' => $chargeId]);
                    return response()->json(['success' => true]);
                    
                case 'FAILED':
                case 'CANCELLED':
                case 'DECLINED':
                    Log::info('Payment verified as failed', [
                        'chargeId' => $chargeId,
                        'status' => $status
                    ]);
                    return response()->json(['failed' => true]);
                    
                case 'INITIATED':
                case 'PENDING':
                case 'AUTHORIZED':
                default:
                    Log::info('Payment still pending', [
                        'chargeId' => $chargeId,
                        'status' => $status
                    ]);
                    return response()->json(['success' => false]);
            }

        } catch (\Exception $e) {
            Log::error('Payment verification failed', [
                'error' => $e->getMessage(),
                'chargeId' => $request->input('chargeId'),
                'transactionId' => $request->input('transactionId')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create refund
     */
    public function refundPayment(Request $request)
    {
        try {
            $amount = $request->input('amount');
            $transactionId = $request->input('transactionId');
            $locationId = $request->input('locationId');
            $apiKey = $request->input('apiKey');

            Log::info('Creating refund', [
                'amount' => $amount,
                'transactionId' => $transactionId,
                'locationId' => $locationId
            ]);

            // Find user by location ID
            $user = User::where('lead_location_id', $locationId)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found for location'
                ], 404);
            }

            // Get API keys from user
            $tapApiKey = $user->lead_test_api_key ?? $user->lead_live_api_key;
            if (!$tapApiKey) {
                return response()->json([
                    'success' => false,
                    'message' => 'API keys not configured'
                ], 400);
            }

            // Initialize Tap service
            $tapService = new TapPaymentService($tapApiKey, '');

            // Create refund
            $refund = $tapService->createRefund($transactionId, $amount);
            
            if (!$refund) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create refund'
                ], 500);
            }

            Log::info('Refund created successfully', ['refund' => $refund]);

            return response()->json([
                'success' => true,
                'refund' => $refund
            ]);

        } catch (\Exception $e) {
            Log::error('Refund creation failed', [
                'error' => $e->getMessage(),
                'amount' => $request->input('amount'),
                'transactionId' => $request->input('transactionId')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Refund creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List payment methods (for card on file - not implemented yet)
     */
    public function listPaymentMethods(Request $request)
    {
        Log::info('List payment methods requested', $request->all());
        
        // Return empty array for now - card on file not implemented
        return response()->json([]);
    }

    /**
     * Charge saved payment method (for card on file - not implemented yet)
     */
    public function chargePaymentMethod(Request $request)
    {
        Log::info('Charge payment method requested', $request->all());
        
        return response()->json([
            'success' => false,
            'message' => 'Saved payment methods not yet implemented'
        ], 501);
    }

    /**
     * Create subscription (for recurring payments - not implemented yet)
     */
    public function createSubscription(Request $request)
    {
        Log::info('Create subscription requested', $request->all());
        
        return response()->json([
            'success' => false,
            'message' => 'Subscriptions not yet implemented'
        ], 501);
    }

    /**
     * Payment success page handler
     */
    public function paymentSuccess(Request $request)
    {
        $chargeId = $request->input('charge_id');
        
        Log::info('Payment success page accessed', [
            'chargeId' => $chargeId,
            'query' => $request->query()
        ]);

        return view('payment.success', compact('chargeId'));
    }

    /**
     * Verify charge status (for frontend AJAX calls)
     */
    public function verifyCharge(Request $request)
    {
        try {
            $chargeId = $request->input('chargeId');
            $transactionId = $request->input('transactionId');
            $orderId = $request->input('orderId');

            Log::info('Frontend charge verification', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'orderId' => $orderId
            ]);

            if (!$chargeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Charge ID is required'
                ], 400);
            }

            // Get the first user for now (in production, you'd need to identify the correct user)
            $user = User::whereNotNull('lead_test_api_key')->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'No user found with API keys'
                ], 404);
            }

            $tapApiKey = $user->lead_test_api_key;
            $tapService = new TapPaymentService($tapApiKey, '');

            // Retrieve charge from Tap
            $charge = $tapService->retrieveCharge($chargeId);
            
            if (!$charge) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve charge information'
                ], 500);
            }

            $status = $charge['status'] ?? 'UNKNOWN';
            
            switch ($status) {
                case 'CAPTURED':
                    return response()->json([
                        'success' => true,
                        'status' => 'succeeded',
                        'charge' => $charge
                    ]);
                    
                case 'FAILED':
                case 'CANCELLED':
                case 'DECLINED':
                    return response()->json([
                        'success' => false,
                        'failed' => true,
                        'status' => 'failed',
                        'charge' => $charge
                    ]);
                    
                default:
                    return response()->json([
                        'success' => false,
                        'status' => 'pending',
                        'charge' => $charge
                    ]);
            }

        } catch (\Exception $e) {
            Log::error('Frontend charge verification failed', [
                'error' => $e->getMessage(),
                'chargeId' => $request->input('chargeId')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Verification failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
