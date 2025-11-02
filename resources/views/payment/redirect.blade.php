{{-- 
  resources/views/payment/redirect.blade.php
  
  Payment redirect handler for Tap Charge API
  This page handles the redirect after payment completion
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Payment Redirect - {{ config('app.name', 'Laravel') }}</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>

  </style>
</head>
<body>
  
  <script>
    let chargeData = null;

    // Check if this page is opened in a popup
    function isPopup() {
      return window.opener && window.opener !== window;
    }

    // Send message to parent window if in popup
    function sendMessageToParent(message) {
      if (isPopup() && window.opener) {
        try {
          window.opener.postMessage(message, '*');
          console.log('📤 Sent message to parent window:', message);
        } catch (error) {
          console.error('❌ Failed to send message to parent:', error);
        }
      }
    }

    // Parse URL parameters
    function getUrlParams() {
      const params = new URLSearchParams(window.location.search);
      console.log('params', params);
      console.log('window', window);

      console.log('location', window.location);

      return {
        tap_id: params.get('tap_id'),
        charge_id: params.get('charge_id'),
        status: params.get('status'),
        amount: params.get('amount'),
        currency: params.get('currency'),
        transaction_id: params.get('transaction_id'),
        order_id: params.get('order_id')
      };
    }

    // Fetch charge status from API
    async function fetchChargeStatus(tapId) {
      try {
        console.log('🔍 Fetching charge status for tap_id:', tapId);
        
        // Get locationId from URL parameters or use a default
        const urlParams = new URLSearchParams(window.location.search);
        const locationId = urlParams.get('locationId') || 'xAN9Y8iZDOugbNvKBKad'; // Default locationId
        
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

    // Update UI based on payment status
    function updatePaymentStatus(params) {
      const statusMessage = document.getElementById('status-message');
      const paymentStatus = document.getElementById('payment-status');
      const actionButtons = document.getElementById('action-buttons');
      const loadingSpinner = document.getElementById('loading-spinner');
      const redirectTitle = document.getElementById('redirect-title');
      const redirectMessage = document.getElementById('redirect-message');

      // Hide loading spinner
      loadingSpinner.style.display = 'none';

      // Update status display
      document.getElementById('charge-id').textContent = params.charge_id || '-';
      document.getElementById('charge-status').textContent = params.status || '-';
      document.getElementById('charge-amount').textContent = params.amount || '-';
      document.getElementById('charge-currency').textContent = params.currency || '-';

      paymentStatus.style.display = 'block';

      // Check both processed status and raw status for compatibility
      const isSuccessful = params.is_successful || params.status === 'success' || params.status === 'CAPTURED' || params.status === 'AUTHORIZED';
      const isFailed = params.status === 'failed' || params.status === 'FAILED' || params.status === 'DECLINED' || params.status === 'CANCELLED' || params.status === 'REVERSED';
      
      if (isSuccessful) {
        // Payment successful - auto-send success and redirect
        // Auto-send success response to GHL
        sendSuccessToGHL();
          
          // Auto-redirect to MediaSolution preview page after 2 seconds
          setTimeout(() => {
            window.location.href = 'https://app.mediasolution.io/v2/preview/hjlbiZ2niIjjWetPSvT5';
          }, 2000);
      } 

      statusMessage.style.display = 'block';
    }

    // Send success response to GoHighLevel
    function sendSuccessToGHL() {
      const params = getUrlParams();
      
      // Get charge ID from either the stored charge data or URL params
      const chargeId = chargeData?.charge?.id || params.charge_id || params.tap_id;
      
      const successEvent = {
        type: 'custom_element_success_response',
        chargeId: chargeId
      };
      
      console.log('✅ Sending success response to GHL:', successEvent);
      console.log('📊 Charge data available:', chargeData);
      
      sendMessageToGHL(successEvent);
      
      // Show success message
      document.getElementById('redirect-title').textContent = 'Payment Complete';
      document.getElementById('redirect-message').textContent = 'Success response sent to GoHighLevel.';
      
      // Hide action buttons
      document.getElementById('action-buttons').style.display = 'none';
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

    // Send close response to GoHighLevel
    function sendCloseToGHL() {
      const closeEvent = {
        type: 'custom_element_close_response'
      };
      
      console.log('🚪 Sending close response to GHL:', closeEvent);
      sendMessageToGHL(closeEvent);
    }

    // Generic function to send messages to GoHighLevel
    function sendMessageToGHL(message) {
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(message, '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(message, '*');
        }
      } catch (error) {
        console.warn('⚠️ Could not send message to parent:', error.message);
      }
    }

    // Go back to previous page
    function goBack() {
      if (window.history.length > 1) {
        window.history.back();
      } else {
        window.close();
      }
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', async function() {
      console.log('🚀 Payment Redirect Handler Loaded');
      
      const params = getUrlParams();
      console.log('📋 URL Parameters:', params);
      
      if (params.tap_id) {
        try {
          // Fetch charge status from Tap API
          chargeData = await fetchChargeStatus(params.tap_id);
          
          // Update UI with the retrieved charge data
          const updatedParams = {
            ...params,
            charge_id: chargeData.charge.id,
            status: chargeData.payment_status, // Use processed payment status instead of raw status
            amount: chargeData.amount,
            currency: chargeData.currency,
            transaction_id: chargeData.transaction_id,
            order_id: chargeData.order_id,
            is_successful: chargeData.is_successful,
            raw_status: chargeData.charge.status // Keep raw status for reference
          };
          
          console.log('📊 Updated params for UI:', updatedParams);
          updatePaymentStatus(updatedParams);
          
          // Auto-send success is now handled in updatePaymentStatus function
        } catch (error) {
          console.error('❌ Failed to fetch charge status:', error);
          
          // Hide loading spinner
          document.getElementById('loading-spinner').style.display = 'none';
          
          // Show error message
          document.getElementById('redirect-title').textContent = 'Error Loading Payment';
          document.getElementById('redirect-message').textContent = 'Unable to retrieve payment status. Please try again or contact support.';
          document.getElementById('action-buttons').style.display = 'flex';
        }
      } else if (params.charge_id) {
        // Fallback to old behavior if charge_id is present but no tap_id
        updatePaymentStatus(params);
      } else {
        // No charge ID or tap_id in URL, show error
        document.getElementById('loading-spinner').style.display = 'none';
        document.getElementById('redirect-title').textContent = 'Invalid Redirect';
        document.getElementById('redirect-message').textContent = 'No payment information found in the redirect URL.';
        document.getElementById('action-buttons').style.display = 'flex';
      }
    });

    // Auto-send success if in iframe and payment is successful
    if (window !== window.top) {
      // This will be handled in the DOMContentLoaded event after we fetch the charge status
      window.autoSendSuccess = true;
    }
  </script>
</body>
</html>
