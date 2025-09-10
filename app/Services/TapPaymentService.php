<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TapPaymentService
{
    private $apiKey;
    private $publishableKey;
    private $baseUrl;
    private $isLive;

    public function __construct($apiKey, $publishableKey, $isLive = false)
    {
        $this->apiKey = $apiKey ?? '5tap61';
        $this->publishableKey = $publishableKey ?? 'pk_test_xItqaSsJzl5g2K08fCwYbMvQ';
        $this->isLive = $isLive ?? false;
        $this->baseUrl = $isLive ? 'https://api.tap.company/v2' : 'https://api.tap.company/v2';
    }

    /**
     * Create a charge using Tap token
     */
    public function createCharge($token, $amount, $currency = 'JOD', $customer = null, $description = null)
    {
        try {
            $payload = [
                'amount' => $amount,
                'currency' => $currency,
                'source' => [
                    'id' => $token
                ],
                'redirect' => [
                    'url' => config('app.url') . '/payment/redirect'
                ]
            ];

            if ($customer) {
                $payload['customer'] = $customer;
            }

            if ($description) {
                $payload['description'] = $description;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/charges', $payload);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap charge creation failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'payload' => $payload
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap charge creation error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create a refund
     */
    public function createRefund($chargeId, $amount = null)
    {
        try {
            $payload = [];
            if ($amount) {
                $payload['amount'] = $amount;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/refunds', array_merge([
                'charge_id' => $chargeId
            ], $payload));

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap refund creation failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap refund creation error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Retrieve a charge
     */
    public function retrieveCharge($chargeId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/charges/' . $chargeId);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap charge retrieval failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap charge retrieval error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create a customer
     */
    public function createCustomer($customerData)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/customers', $customerData);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap customer creation failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap customer creation error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create a subscription
     */
    public function createSubscription($subscriptionData)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/subscriptions', $subscriptionData);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap subscription creation failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap subscription creation error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Update a subscription
     */
    public function updateSubscription($subscriptionId, $updateData)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->put($this->baseUrl . '/subscriptions/' . $subscriptionId, $updateData);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap subscription update failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap subscription update error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Cancel a subscription
     */
    public function cancelSubscription($subscriptionId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->delete($this->baseUrl . '/subscriptions/' . $subscriptionId);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap subscription cancellation failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap subscription cancellation error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get customer payment methods
     */
    public function getCustomerPaymentMethods($customerId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/customers/' . $customerId . '/cards');

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap payment methods retrieval failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap payment methods retrieval error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create a customer
     */
    public function createCustomer($customerData)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/customers', $customerData);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap customer creation failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap customer creation error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create a charge using saved payment method
     */
    public function createChargeWithPaymentMethod($paymentMethodId, $amount, $currency = 'JOD', $customerId = null, $description = null)
    {
        try {
            $payload = [
                'amount' => $amount,
                'currency' => $currency,
                'source' => [
                    'id' => $paymentMethodId
                ],
                'redirect' => [
                    'url' => config('app.url') . '/payment/redirect'
                ]
            ];

            if ($customerId) {
                $payload['customer'] = $customerId;
            }

            if ($description) {
                $payload['description'] = $description;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/charges', $payload);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap charge with payment method failed', [
                    'status' => $response->status(),
                    'response' => $response->json(),
                    'payload' => $payload
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap charge with payment method error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Save a card for future use
     */
    public function saveCard($token, $customerId)
    {
        try {
            $payload = [
                'source' => $token,
                'customer' => $customerId
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/cards', $payload);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap save card failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap save card error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Delete a saved card
     */
    public function deleteCard($cardId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])->delete($this->baseUrl . '/cards/' . $cardId);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Tap delete card failed', [
                    'status' => $response->status(),
                    'response' => $response->json()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Tap delete card error', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
