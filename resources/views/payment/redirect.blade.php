{{-- 
  resources/views/payment/redirect.blade.php
  
  Payment redirect handler for Tap Charge API
  This page handles the redirect after payment completion silently
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Processing Payment...</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      background: transparent;
    }
  </style>
</head>
<body>
  <script>
    // Parse URL parameters
    function getUrlParams() {
      const params = new URLSearchParams(window.location.search);
      return {
        tap_id: params.get('tap_id'),
        charge_id: params.get('charge_id'),
        status: params.get('status'),
        amount: params.get('amount'),
        currency: params.get('currency'),
        transaction_id: params.get('transaction_id'),
        order_id: params.get('order_id'),
        cancel: params.get('cancel')
      };
    }

    // Fetch charge status from API
    async function fetchChargeStatus(tapId) {
      try {
        
        const urlParams = new URLSearchParams(window.location.search);
        const locationId = urlParams.get('locationId');
        
        // If locationId is missing, we can't fetch from API, but we'll handle this in processPayment
        if (!locationId) {
          console.warn('⚠️ Location ID not found in URL parameters. Will use URL parameters as fallback.');
          throw new Error('Location ID is required for API call');
        }
        
        const response = await fetch(`/charge/status?tap_id=${encodeURIComponent(tapId)}&locationId=${encodeURIComponent(locationId)}`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          }
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();

        if (data.success) {
          return data;
        } else {
          throw new Error(data.message || 'Failed to retrieve charge status');
        }
      } catch (error) {
        console.error('❌ Error fetching charge status:', error);
        throw error;
      }
    }

    // Send message to GoHighLevel
    function sendMessageToGHL(message) {
      try {
        let messageSent = false;
        
        // PRIORITY 1: Try postMessage for popup scenarios (Safari workaround)
        // This is the primary method when opened in a popup window
        if (window.opener && window.opener !== window) {
          try {
            window.opener.postMessage(JSON.stringify(message), '*');
            messageSent = true;
            // If we successfully sent via opener, we're done (don't try other methods)
            return;
          } catch (error) {
            console.warn('⚠️ Could not send message via window.opener:', error.message);
          }
        }
        
        // PRIORITY 2: Try postMessage for iframe scenarios
        if (window.parent && window.parent !== window) {
          try {
            window.parent.postMessage(JSON.stringify(message), '*');
            messageSent = true;
          } catch (error) {
            console.warn('⚠️ Could not send message via window.parent:', error.message);
          }
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          try {
            window.top.postMessage(JSON.stringify(message), '*');
            messageSent = true;
          } catch (error) {
            console.warn('⚠️ Could not send message via window.top:', error.message);
          }
        }
        
        // PRIORITY 3: Fallback: Use localStorage for cross-tab communication (Safari new tab scenario)
        // This works when the page is opened in a new tab instead of an iframe or popup
        if (!messageSent || window.parent === window) {
          try {
            const storageKey = 'ghl_payment_message_' + Date.now();
            const messageData = {
              message: message,
              timestamp: Date.now(),
              source: 'payment_redirect'
            };
            
            localStorage.setItem(storageKey, JSON.stringify(messageData));
            
            // Clean up old messages after a short delay
            setTimeout(() => {
              try {
                localStorage.removeItem(storageKey);
              } catch (e) {
                // Ignore cleanup errors
              }
            }, 1000);
            
            messageSent = true;
          } catch (error) {
            console.warn('⚠️ Could not send message via localStorage:', error.message);
          }
        }
        
        if (!messageSent) {
          console.warn('⚠️ Could not send message via any method');
        }
      } catch (error) {
        console.warn('⚠️ Could not send message to parent:', error.message);
      }
    }

    // Send success response to GoHighLevel and redirect
    function sendSuccessToGHL(chargeId) {
      const successEvent = {
        type: 'custom_element_success_response',
        chargeId: chargeId
      };
      
      sendMessageToGHL(successEvent);
      
    }

    // Send error response to GoHighLevel
    function sendErrorToGHL(errorMessage) {
      const errorEvent = {
        type: 'custom_element_error_response',
        error: {
          description: errorMessage
        }
      };
      
      sendMessageToGHL(errorEvent);
    }

    // Send close response to GoHighLevel (for canceled payments)
    function sendCloseToGHL() {
      const closeEvent = {
        type: 'custom_element_close_response'
      };
      
      sendMessageToGHL(closeEvent);
    }

    // Process payment status
    async function processPayment() {
      const params = getUrlParams();

      // Check if payment was canceled
      if (params.cancel === 'true' || params.status === 'canceled' || params.status === 'CANCELLED') {
        sendCloseToGHL();
        return;
      }

      // If we have tap_id, try to fetch the charge status from API
      if (params.tap_id) {
        try {
          const chargeData = await fetchChargeStatus(params.tap_id);

          const status = chargeData.payment_status || chargeData.charge?.status || params.status;
          const isSuccessful = chargeData.is_successful || status === 'success' || status === 'CAPTURED' || status === 'AUTHORIZED';
          const isFailed = status === 'failed' || status === 'FAILED' || status === 'DECLINED' || status === 'REVERSED';
          const isCanceled = status === 'CANCELLED' || status === 'canceled' || status === 'cancelled';
          
          const chargeId = chargeData.charge?.id || params.charge_id || params.tap_id;
          
          if (isSuccessful) {
            // Payment successful
            sendSuccessToGHL(chargeId);
          } else if (isCanceled) {
            // Payment canceled
            sendCloseToGHL();
          } else if (isFailed) {
            // Payment failed
            const errorMessage = chargeData.charge?.response?.message || chargeData.message || 'Payment failed';
            sendErrorToGHL(errorMessage);
          } else {
            // Unknown status - fall through to URL params fallback
            console.warn('⚠️ Unknown status from API, falling back to URL parameters');
            throw new Error('Unknown payment status from API');
          }
        } catch (error) {
          console.warn('⚠️ Failed to fetch charge status from API, using URL parameters as fallback:', error.message);
          
          // FALLBACK: Use URL parameters to determine payment status
          // Tap usually includes status in the redirect URL
          const status = params.status?.toUpperCase();
          const chargeId = params.charge_id || params.tap_id;
          
          
          if (status === 'SUCCESS' || status === 'CAPTURED' || status === 'AUTHORIZED') {
            sendSuccessToGHL(chargeId);
          } else if (status === 'CANCELLED' || status === 'CANCELED') {
            sendCloseToGHL();
          } else if (status === 'FAILED' || status === 'DECLINED') {
            sendErrorToGHL('Payment failed');
          } else if (status) {
            // We have a status but it's not recognized
            // Try to infer success from common success indicators
            if (status.includes('SUCCESS') || status.includes('CAPTURED') || status.includes('AUTHORIZED')) {
              sendSuccessToGHL(chargeId);
            } else if (status.includes('FAIL') || status.includes('DECLINED')) {
              sendErrorToGHL('Payment failed');
            } else {
              // Last resort: if we have a charge_id/tap_id but unknown status, assume it might be successful
              // (Tap sometimes doesn't include status in URL for successful payments)
              sendSuccessToGHL(chargeId);
            }
          } else {
            // No status in URL either - this is a real error
            console.error('❌ No payment status available from API or URL');
            sendErrorToGHL('Unable to retrieve payment status. Please try again or contact support.');
          }
        }
      } else if (params.charge_id) {
        // Fallback: use status from URL params (no tap_id, but we have charge_id)
        const status = params.status?.toUpperCase();
        
        if (status === 'SUCCESS' || status === 'CAPTURED' || status === 'AUTHORIZED') {
          sendSuccessToGHL(params.charge_id);
        } else if (status === 'CANCELLED' || status === 'CANCELED') {
          sendCloseToGHL();
        } else if (status === 'FAILED' || status === 'DECLINED') {
          sendErrorToGHL('Payment failed');
        } else {
          // No status but we have charge_id - might be successful
          sendSuccessToGHL(params.charge_id);
        }
      } else {
        // No payment information
        sendErrorToGHL('No payment information found in the redirect URL.');
      }
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
     processPayment();
    });
  </script>
</body>
</html>
