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
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .redirect-container {
      background: white;
      border-radius: 20px;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
      max-width: 480px;
      width: 100%;
      overflow: hidden;
      position: relative;
      text-align: center;
      padding: 40px 30px;
    }

    .loading-spinner {
      width: 40px;
      height: 40px;
      border: 4px solid #f3f3f3;
      border-top: 4px solid #667eea;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 20px;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .redirect-title {
      font-size: 24px;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 12px;
    }

    .redirect-message {
      font-size: 16px;
      color: #6b7280;
      margin-bottom: 30px;
      line-height: 1.5;
    }

    .payment-status {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 30px;
    }

    .status-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid #e5e7eb;
    }

    .status-item:last-child {
      border-bottom: none;
    }

    .status-label {
      font-weight: 500;
      color: #374151;
    }

    .status-value {
      color: #6b7280;
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
      font-size: 14px;
    }

    .success-badge {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #16a34a;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 500;
    }

    .error-badge {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      font-weight: 500;
    }

    .action-buttons {
      display: flex;
      gap: 12px;
      justify-content: center;
    }

    .btn {
      padding: 12px 24px;
      border-radius: 8px;
      font-weight: 600;
      text-decoration: none;
      transition: all 0.3s ease;
      border: none;
      cursor: pointer;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary {
      background: #f3f4f6;
      color: #374151;
      border: 1px solid #d1d5db;
    }

    .btn-secondary:hover {
      background: #e5e7eb;
    }

    @media (max-width: 480px) {
      .redirect-container {
        margin: 10px;
        padding: 30px 20px;
      }
      
      .redirect-title {
        font-size: 20px;
      }
      
      .action-buttons {
        flex-direction: column;
      }
    }
  </style>
</head>
<body>
  <div class="redirect-container">
    <div class="loading-spinner" id="loading-spinner"></div>
    
    <h1 class="redirect-title" id="redirect-title">Processing Payment...</h1>
    <p class="redirect-message" id="redirect-message">Please wait while we process your payment.</p>

    <div class="payment-status" id="payment-status" style="display: none;">
      <div class="status-item">
        <span class="status-label">Charge ID:</span>
        <span class="status-value" id="charge-id">-</span>
      </div>
      <div class="status-item">
        <span class="status-label">Status:</span>
        <span class="status-value" id="charge-status">-</span>
      </div>
      <div class="status-item">
        <span class="status-label">Amount:</span>
        <span class="status-value" id="charge-amount">-</span>
      </div>
      <div class="status-item">
        <span class="status-label">Currency:</span>
        <span class="status-value" id="charge-currency">-</span>
      </div>
    </div>

    <div id="status-message" style="display: none;"></div>

    <div class="action-buttons" id="action-buttons" style="display: none;">
      <button class="btn btn-primary" onclick="sendSuccessToGHL()">
        <i class="fas fa-check"></i> Complete Payment
      </button>
      <button class="btn btn-secondary" onclick="goBack()">
        <i class="fas fa-arrow-left"></i> Go Back
      </button>
    </div>
  </div>

  <script>
    let chargeData = null;

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
        statusMessage.innerHTML = '<div class="success-badge"><i class="fas fa-check-circle"></i> Payment Successful!</div>';
        redirectTitle.textContent = 'Payment Complete';
        redirectMessage.textContent = 'Your payment has been processed successfully. Redirecting...';
        
        // Auto-send success response to GHL
        sendSuccessToGHL();
        
        // Auto-close window after 3 seconds if opened from iframe
        setTimeout(() => {
          if (window.opener) {
            // If opened in new window, close it
            console.log('🔍 Closing payment window (opened from iframe)');
            window.close();
          } else if (isSafariIframe()) {
            // For Safari iframes, try to communicate with parent and then redirect
            console.log('🍎 Safari iframe - sending success message to parent');
            sendMessageToParent({
              type: 'payment_completed',
              success: true,
              chargeId: chargeData?.charge?.id,
              data: chargeData
            });
            
            // Redirect to MediaSolution preview page
            window.location.href = 'https://app.mediasolution.io/v2/preview/FHNVMDKeSCxgu8V07UUO';
          } else {
            // If in iframe, redirect to MediaSolution preview page
            window.location.href = 'https://app.mediasolution.io/v2/preview/FHNVMDKeSCxgu8V07UUO';
          }
        }, 3000);
        
        // Hide action buttons since we're auto-processing
        actionButtons.style.display = 'none';
      } else if (isFailed) {
        // Payment failed
        statusMessage.innerHTML = '<div class="error-badge"><i class="fas fa-times-circle"></i> Payment Failed</div>';
        redirectTitle.textContent = 'Payment Failed';
        redirectMessage.textContent = 'Your payment could not be processed. Please try again.';
        actionButtons.style.display = 'flex';
        
        // Send error response to GoHighLevel
        const errorMessage = params.message || 'Payment failed. Please try again.';
        sendErrorToGHL(errorMessage);
      } else {
        // Unknown status - show both processed and raw status for debugging
        statusMessage.innerHTML = `<div class="error-badge"><i class="fas fa-exclamation-triangle"></i> Unknown Payment Status</div>
          <div style="margin-top: 10px; font-size: 12px; color: #666;">
            Processed Status: ${params.status || 'N/A'}<br>
            Raw Status: ${params.raw_status || 'N/A'}<br>
            Is Successful: ${params.is_successful || 'N/A'}
          </div>`;
        redirectTitle.textContent = 'Payment Status Unknown';
        redirectMessage.textContent = 'We could not determine the payment status. Please contact support.';
        actionButtons.style.display = 'flex';
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
      
      // Also send message to parent window if opened from iframe
      sendMessageToParent({
        type: 'payment_completed',
        success: true,
        chargeId: chargeId,
        data: chargeData
      });
      
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
      
      // Also send message to parent window if opened from iframe
      sendMessageToParent({
        type: 'payment_completed',
        success: false,
        error: errorMessage
      });
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

    // Function to send messages to parent window (for iframe communication)
    function sendMessageToParent(message) {
      try {
        if (window.opener) {
          // If opened in a new window, send to opener
          window.opener.postMessage(message, '*');
          console.log('📤 Sent message to opener window:', message);
        } else if (window.parent && window.parent !== window) {
          // If in iframe, send to parent
          window.parent.postMessage(message, '*');
          console.log('📤 Sent message to parent window:', message);
        } else {
          console.log('⚠️ No parent window or opener available');
        }
      } catch (error) {
        console.warn('⚠️ Could not send message to parent/opener:', error.message);
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

    // Safari detection
    function isSafari() {
      const ua = navigator.userAgent.toLowerCase();
      return ua.includes('safari') && !ua.includes('chrome') && !ua.includes('crios') && !ua.includes('fxios');
    }

    // Safari mobile detection
    function isSafariMobile() {
      const ua = navigator.userAgent.toLowerCase();
      return ua.includes('safari') && ua.includes('mobile') && !ua.includes('chrome');
    }

    // Check if we're in Safari iframe
    function isSafariIframe() {
      return isSafari() && window !== window.top;
    }

    console.log('🔍 Safari detection:', {
      isSafari: isSafari(),
      isSafariMobile: isSafariMobile(),
      isSafariIframe: isSafariIframe(),
      userAgent: navigator.userAgent
    });

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
