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
      console.log('📊 URL Parameters:', params);
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
        console.log('🔍 Fetching charge status for tap_id:', tapId);
        
        const urlParams = new URLSearchParams(window.location.search);
        const locationId = urlParams.get('locationId') || 'xAN9Y8iZDOugbNvKBKad';
        
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
        console.log('📊 Charge status response:', data);

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
        console.log('📤 Sending message to GHL:', message);
        
        let messageSent = false;
        
        // Try postMessage for iframe scenarios
        if (window.parent && window.parent !== window) {
          try {
            window.parent.postMessage(JSON.stringify(message), '*');
            messageSent = true;
            console.log('✅ Message sent via window.parent.postMessage');
          } catch (error) {
            console.warn('⚠️ Could not send message via window.parent:', error.message);
          }
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          try {
            window.top.postMessage(JSON.stringify(message), '*');
            messageSent = true;
            console.log('✅ Message sent via window.top.postMessage');
          } catch (error) {
            console.warn('⚠️ Could not send message via window.top:', error.message);
          }
        }
        
        // Fallback: Use localStorage for cross-tab communication (Safari new tab scenario)
        // This works when the page is opened in a new tab instead of an iframe
        if (!messageSent || window.parent === window) {
          try {
            const storageKey = 'ghl_payment_message_' + Date.now();
            const messageData = {
              message: message,
              timestamp: Date.now(),
              source: 'payment_redirect'
            };
            
            localStorage.setItem(storageKey, JSON.stringify(messageData));
            console.log('✅ Message sent via localStorage (cross-tab communication):', storageKey);
            
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
      
      console.log('✅ Sending success response to GHL:', successEvent);
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
      
      console.log('❌ Sending error response to GHL:', errorEvent);
      sendMessageToGHL(errorEvent);
    }

    // Send close response to GoHighLevel (for canceled payments)
    function sendCloseToGHL() {
      const closeEvent = {
        type: 'custom_element_close_response'
      };
      
      console.log('🚪 Sending close response to GHL (payment canceled):', closeEvent);
      sendMessageToGHL(closeEvent);
    }

    // Process payment status
    async function processPayment() {
      const params = getUrlParams();
      console.log('📋 URL Parameters:', params);

      // Check if payment was canceled
      if (params.cancel === 'true' || params.status === 'canceled' || params.status === 'CANCELLED') {
        console.log('🚪 Payment was canceled');
        sendCloseToGHL();
        return;
      }

      // If we have tap_id, fetch the charge status
      if (params.tap_id) {
        try {
          const chargeData = await fetchChargeStatus(params.tap_id);

          console.log('📊 Charge data:', chargeData);
          const status = chargeData.payment_status || chargeData.charge?.status || params.status;
          console.log('📊 Status:', status);
          const isSuccessful = chargeData.is_successful || status === 'success' || status === 'CAPTURED' || status === 'AUTHORIZED';
          console.log('📊 Is successful:', isSuccessful);
          const isFailed = status === 'failed' || status === 'FAILED' || status === 'DECLINED' || status === 'REVERSED';
          console.log('📊 Is failed:', isFailed);
          const isCanceled = status === 'CANCELLED' || status === 'canceled' || status === 'cancelled';
          console.log('📊 Is canceled:', isCanceled);
          
          const chargeId = chargeData.charge?.id || params.charge_id || params.tap_id;
          console.log('📊 Charge ID:', chargeId);
          
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
            // Unknown status
            sendErrorToGHL('Unknown payment status');
          }
        } catch (error) {
          console.error('❌ Failed to fetch charge status:', error);
          sendErrorToGHL('Unable to retrieve payment status. Please try again or contact support.');
        }
      } else if (params.charge_id) {
        // Fallback: use status from URL params
        const status = params.status?.toUpperCase();
        
        if (status === 'SUCCESS' || status === 'CAPTURED' || status === 'AUTHORIZED') {
          sendSuccessToGHL(params.charge_id);
        } else if (status === 'CANCELLED' || status === 'CANCELED') {
          sendCloseToGHL();
        } else if (status === 'FAILED' || status === 'DECLINED') {
          sendErrorToGHL('Payment failed');
        } else {
          sendErrorToGHL('Unknown payment status');
        }
      } else {
        // No payment information
        sendErrorToGHL('No payment information found in the redirect URL.');
      }
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🚀 Payment Redirect Handler Loaded');
     processPayment();
    });
  </script>
</body>
</html>
