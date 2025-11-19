<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Send payment.captured webhook event to LeadConnector backend
     * This should be called when a payment is in a final state (succeeded, failed, or pending)
     */
    public function sendPaymentCapturedWebhook(User $user, string $chargeId, string $transactionId, array $chargeData)
    {
        try {
            $locationId = $user->lead_location_id;
            $apiKey = $user->tap_mode === 'live' ? $user->lead_live_api_key : $user->lead_test_api_key;
            $isLive = $user->tap_mode === 'live';
            
            // Validate API key exists for the current mode
            if (empty($apiKey)) {
                Log::error('=== WEBHOOK ERROR: API key missing for current mode ===', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'locationId' => $locationId,
                    'tap_mode' => $user->tap_mode,
                    'is_live' => $isLive,
                    'has_live_key' => !empty($user->lead_live_api_key),
                    'has_test_key' => !empty($user->lead_test_api_key)
                ]);
                return false; // Cannot send webhook without API key
            }
            
            Log::info('=== SENDING payment.captured WEBHOOK TO LEADCONNECTOR ===', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'locationId' => $locationId,
                'status' => $chargeData['status'] ?? 'UNKNOWN',
                'tap_mode' => $user->tap_mode,
                'is_live' => $isLive,
                'api_key_length' => strlen($apiKey),
                'api_key_prefix' => substr($apiKey, 0, 10) . '...',
                'timestamp' => now()->toIso8601String()
            ]);
            
            // Build chargeSnapshot from Tap API charge data
            // GHL expects: status (enum: 'succeeded', 'failed', 'pending'), amount, chargeId, chargedAt
            // Note: amount should be in 100s (actual amount * 100, e.g., 0.10 KWD = 10)
            $transactionCreated = $chargeData['transaction']['created'] ?? $chargeData['created'] ?? time() * 1000;
            $chargedAt = is_numeric($transactionCreated) ? (int)$transactionCreated : strtotime($transactionCreated) * 1000;
            
            // Convert chargedAt from milliseconds to seconds (unix timestamp) as per GHL docs
            $chargedAtSeconds = (int)($chargedAt / 1000);
            
            // Map Tap payment status to GHL chargeSnapshot.status
            // GHL expects: 'succeeded', 'failed', or 'pending'
            $tapStatus = strtoupper($chargeData['status'] ?? 'UNKNOWN');
            $ghlStatus = 'pending'; // default
            
            if (in_array($tapStatus, ['CAPTURED', 'AUTHORIZED'])) {
                $ghlStatus = 'succeeded';
            } elseif (in_array($tapStatus, ['FAILED', 'DECLINED', 'CANCELLED', 'REVERSED'])) {
                $ghlStatus = 'failed';
            } else {
                $ghlStatus = 'pending';
            }
            
            // Convert amount to 100s (multiply by 100) as per GHL docs
            // Example: 0.10 KWD becomes 10
            $amountInHundreds = (int)round(($chargeData['amount'] ?? 0) * 100);
            
            $chargeSnapshot = [
                'status' => $ghlStatus, // 'succeeded', 'failed', or 'pending'
                'amount' => $amountInHundreds, // Amount in 100s (actual amount * 100)
                'chargeId' => $chargeId,
                'chargedAt' => $chargedAtSeconds // Unix timestamp in seconds
            ];
            
            Log::info('ChargeSnapshot built for webhook', [
                'chargeId' => $chargeId,
                'tap_status' => $tapStatus,
                'ghl_status' => $ghlStatus,
                'amount_original' => $chargeData['amount'] ?? 0,
                'amount_in_hundreds' => $amountInHundreds,
                'chargedAt_seconds' => $chargedAtSeconds
            ]);
            
            // Validate required fields before building payload
            // GHL requires: event, chargeId, ghlTransactionId, chargeSnapshot, locationId, apiKey
            // chargeSnapshot requires: status, amount, chargeId, chargedAt
            $validationErrors = [];
            if (empty($chargeId)) {
                $validationErrors[] = 'chargeId is missing';
            }
            if (empty($transactionId)) {
                $validationErrors[] = 'ghlTransactionId (transactionId) is missing';
            }
            if (empty($locationId)) {
                $validationErrors[] = 'locationId is missing';
            }
            if (empty($apiKey)) {
                $validationErrors[] = 'apiKey is missing';
            }
            if (empty($chargeSnapshot['status'])) {
                $validationErrors[] = 'chargeSnapshot.status is missing';
            }
            if (!isset($chargeSnapshot['amount'])) {
                $validationErrors[] = 'chargeSnapshot.amount is missing';
            }
            if (empty($chargeSnapshot['chargeId'])) {
                $validationErrors[] = 'chargeSnapshot.chargeId is missing';
            }
            if (!isset($chargeSnapshot['chargedAt'])) {
                $validationErrors[] = 'chargeSnapshot.chargedAt is missing';
            }
            
            if (!empty($validationErrors)) {
                Log::error('=== WEBHOOK VALIDATION FAILED ===', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'locationId' => $locationId,
                    'validation_errors' => $validationErrors,
                    'chargeSnapshot' => $chargeSnapshot
                ]);
                return false; // Don't send invalid webhook
            }
            
            // Build webhook payload for payment.captured event
            $payload = [
                'event' => 'payment.captured',
                'chargeId' => $chargeId,
                'ghlTransactionId' => $transactionId,
                'chargeSnapshot' => $chargeSnapshot,
                'locationId' => $locationId,
                'apiKey' => $apiKey
            ];
            
            // Send webhook to LeadConnector backend with retry logic
            $webhookUrl = 'https://backend.leadconnectorhq.com/payments/custom-provider/webhook';
            
            Log::info('Webhook payload built for payment.captured', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'locationId' => $locationId,
                'tap_mode' => $user->tap_mode,
                'is_live' => $isLive,
                'apiKey_length' => strlen($apiKey),
                'apiKey_prefix' => substr($apiKey, 0, 10) . '...',
                'payload_keys' => array_keys($payload),
                'chargeSnapshot' => $chargeSnapshot,
                'webhook_url' => $webhookUrl,
                'payload_json' => json_encode($payload) // Log full payload for debugging
            ]);
            
            $maxRetries = 3;
            $retryDelay = 2; // seconds
            $response = null;
            $webhookSuccess = false;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    Log::info('Webhook send attempt', [
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'chargeId' => $chargeId,
                        'transactionId' => $transactionId,
                        'tap_mode' => $user->tap_mode,
                        'is_live' => $isLive,
                        'webhook_url' => $webhookUrl
                    ]);
                    
                    $startTime = microtime(true);
                    $response = Http::timeout(30)
                        ->retry(1, 1000) // Retry once with 1 second delay for network issues
                        ->acceptJson()
                        ->post($webhookUrl, $payload);
                    $endTime = microtime(true);
                    $duration = round(($endTime - $startTime) * 1000, 2);
                    
                    if ($response->successful()) {
                        $responseBody = $response->json();
                        Log::info('=== SUCCESS: payment.captured webhook sent to LeadConnector ===', [
                            'chargeId' => $chargeId,
                            'transactionId' => $transactionId,
                            'locationId' => $locationId,
                            'tap_mode' => $user->tap_mode,
                            'is_live' => $isLive,
                            'response_status' => $response->status(),
                            'response_body' => $responseBody,
                            'duration_ms' => $duration,
                            'attempt' => $attempt,
                            'timestamp' => now()->toIso8601String()
                        ]);
                        $webhookSuccess = true;
                        
                        // Cache that we successfully sent this webhook (expires in 5 minutes to prevent duplicates)
                        $webhookCacheKey = 'webhook_sent_' . $chargeId . '_' . $transactionId;
                        cache()->put($webhookCacheKey, now()->toIso8601String(), 300);
                        
                        break; // Success, exit retry loop
                    } else {
                        $errorResponse = $response->json() ?? $response->body();
                        Log::warning('Webhook send attempt failed', [
                            'attempt' => $attempt,
                            'chargeId' => $chargeId,
                            'transactionId' => $transactionId,
                            'locationId' => $locationId,
                            'status_code' => $response->status(),
                            'response_body' => $errorResponse,
                            'response_raw' => $response->body(),
                            'duration_ms' => $duration,
                            'will_retry' => $attempt < $maxRetries
                        ]);
                        
                        // If it's a client error (4xx), don't retry - it's likely a payload issue
                        if ($response->status() >= 400 && $response->status() < 500) {
                            Log::error('Webhook rejected by server (client error), not retrying', [
                                'status_code' => $response->status(),
                                'response' => $errorResponse
                            ]);
                            break;
                        }
                        
                        // Retry for server errors (5xx) or network issues
                        if ($attempt < $maxRetries) {
                            sleep($retryDelay);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Webhook send attempt exception', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'will_retry' => $attempt < $maxRetries
                    ]);
                    
                    if ($attempt < $maxRetries) {
                        sleep($retryDelay);
                    }
                }
            }
            
            // Log final result
            if (!$webhookSuccess) {
                $errorResponse = $response ? ($response->json() ?? $response->body()) : 'No response received';
                Log::error('=== FAILED: payment.captured webhook send to LeadConnector after all retries ===', [
                    'chargeId' => $chargeId,
                    'transactionId' => $transactionId,
                    'locationId' => $locationId,
                    'tap_mode' => $user->tap_mode,
                    'is_live' => $isLive,
                    'max_retries' => $maxRetries,
                    'final_status_code' => $response ? $response->status() : 'N/A',
                    'final_response_body' => $errorResponse,
                    'final_response_raw' => $response ? $response->body() : 'N/A',
                    'webhook_url' => $webhookUrl,
                    'timestamp' => now()->toIso8601String(),
                    'payload_sent' => $payload // Log payload for debugging
                ]);
            }
            
            return $webhookSuccess;
            
        } catch (\Exception $e) {
            Log::error('=== EXCEPTION: Error sending payment.captured webhook ===', [
                'chargeId' => $chargeId,
                'transactionId' => $transactionId,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toIso8601String()
            ]);
            return false;
        }
    }
}

