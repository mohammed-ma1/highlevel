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

      if (params.status === 'CAPTURED' || params.status === 'success') {
        // Payment successful
        statusMessage.innerHTML = '<div class="success-badge"><i class="fas fa-check-circle"></i> Payment Successful!</div>';
        redirectTitle.textContent = 'Payment Complete';
        redirectMessage.textContent = 'Your payment has been processed successfully.';
        actionButtons.style.display = 'flex';
      } else if (params.status === 'FAILED' || params.status === 'DECLINED' || params.status === 'CANCELLED') {
        // Payment failed
        statusMessage.innerHTML = '<div class="error-badge"><i class="fas fa-times-circle"></i> Payment Failed</div>';
        redirectTitle.textContent = 'Payment Failed';
        redirectMessage.textContent = 'Your payment could not be processed. Please try again.';
        actionButtons.style.display = 'flex';
      } else {
        // Unknown status
        statusMessage.innerHTML = '<div class="error-badge"><i class="fas fa-exclamation-triangle"></i> Unknown Payment Status</div>';
        redirectTitle.textContent = 'Payment Status Unknown';
        redirectMessage.textContent = 'We could not determine the payment status. Please contact support.';
        actionButtons.style.display = 'flex';
      }

      statusMessage.style.display = 'block';
    }

    // Send success response to GoHighLevel
    function sendSuccessToGHL() {
      const params = getUrlParams();
      
      const successEvent = {
        type: 'custom_element_success_response',
        chargeId: params.charge_id
      };
      
      console.log('✅ Sending success response to GHL:', successEvent);
      
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(successEvent, '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(successEvent, '*');
        }
        
        // Show success message
        document.getElementById('redirect-title').textContent = 'Payment Complete';
        document.getElementById('redirect-message').textContent = 'Success response sent to GoHighLevel.';
        
        // Hide action buttons
        document.getElementById('action-buttons').style.display = 'none';
      } catch (error) {
        console.warn('⚠️ Could not send success response to parent:', error.message);
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
          const chargeData = await fetchChargeStatus(params.tap_id);
          
          // Update UI with the retrieved charge data
          const updatedParams = {
            ...params,
            charge_id: chargeData.charge_id,
            status: chargeData.status,
            amount: chargeData.amount,
            currency: chargeData.currency,
            transaction_id: chargeData.transaction_id,
            order_id: chargeData.order_id
          };
          
          updatePaymentStatus(updatedParams);
          
          // Auto-send success if in iframe and payment is successful
          if (window.autoSendSuccess && (chargeData.status === 'CAPTURED' || chargeData.status === 'success')) {
            setTimeout(() => {
              sendSuccessToGHL();
            }, 2000);
          }
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
