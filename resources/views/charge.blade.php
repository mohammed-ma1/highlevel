{{-- 
  resources/views/charge.blade.php
  
  New Charge API Integration with src_all
  This replaces the Card SDK approach with a hosted payment page
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ config('app.name', 'Laravel') }} — Secure Payment</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .payment-container {
      background: transparent;
      max-width: 100%;
      width: 100%;
      position: relative;
    }

    .payment-body {
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }

    .error-message {
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #dc2626;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: none;
      font-size: 14px;
    }

    .popup-payment-content {
      text-align: center;
      max-width: 500px;
      width: 100%;
      background: white;
      border-radius: 24px;
      padding: 60px 40px;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
      border: 1px solid #e5e7eb;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 32px;
      box-sizing: border-box;
    }

    .loading-spinner-container {
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 20px;
    }

    .payment-loading-spinner {
      width: 50px;
      height: 50px;
      border: 4px solid #e5e7eb;
      border-top: 4px solid #4b5563;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .proceed-payment-button {
      background: #1A2B70;
      color: white;
      border: none;
      padding: 16px 32px;
      border-radius: 8px;
      font-size: 18px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      min-width: 280px;
      width: 100%;
      max-width: 400px;
      position: relative;
      box-sizing: border-box;
    }

    .proceed-payment-button:hover {
      background: #152460;
    }

    .proceed-payment-button:active {
      transform: translateY(1px);
    }

    .button-arrow {
      font-size: 20px;
      font-weight: 400;
    }

    .button-text {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }

    .payment-methods-logos {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      flex-wrap: wrap;
      padding: 12px 20px;
      border: 1px solid #e5e7eb;
      border-radius: 20px;
      background: #fafafa;
      width: 100%;
      max-width: 100%;
      box-sizing: border-box;
    }

    .payment-methods-logos img.payment-logo {
      max-width: 100%;
      height: auto;
      display: block;
    }

    @media (max-width: 768px) {
      .popup-payment-content {
        padding: 40px 30px;
        border-radius: 20px;
        max-width: 100%;
        gap: 28px;
      }

      .loading-spinner-container {
        margin-bottom: 16px;
      }

      .payment-loading-spinner {
        width: 45px;
        height: 45px;
        border-width: 3px;
      }

      .proceed-payment-button {
        padding: 14px 28px;
        font-size: 16px;
        min-width: auto;
        width: 100%;
        max-width: 350px;
        gap: 10px;
      }

      .button-arrow {
        font-size: 18px;
      }

      .payment-methods-logos {
        padding: 10px 16px;
        gap: 10px;
      }
    }

    @media (max-width: 480px) {
      .payment-body {
        padding: 10px;
        min-height: calc(100vh - 20px);
      }

      .popup-payment-content {
        padding: 30px 20px;
        border-radius: 16px;
        gap: 24px;
        max-width: 100%;
      }

      .loading-spinner-container {
        margin-bottom: 12px;
      }

      .payment-loading-spinner {
        width: 40px;
        height: 40px;
        border-width: 3px;
      }

      .proceed-payment-button {
        padding: 14px 20px;
        font-size: 15px;
        min-width: auto;
        width: 100%;
        max-width: 100%;
        gap: 8px;
      }

      .button-arrow {
        font-size: 16px;
      }

      .button-text {
        font-size: 15px;
      }

      .payment-methods-logos {
        padding: 8px 12px;
        gap: 8px;
        border-radius: 16px;
      }
    }

    @media (max-width: 360px) {
      .popup-payment-content {
        padding: 25px 15px;
        gap: 20px;
      }

      .proceed-payment-button {
        padding: 12px 18px;
        font-size: 14px;
      }

      .button-text {
        font-size: 14px;
      }

      .payment-methods-logos {
        padding: 6px 10px;
        gap: 6px;
      }
    }
  </style>
</head>
<body>
  <div class="payment-container" id="payment-container" style="display: none;">
    <div class="payment-body" id="payment-body">
      <!-- Payment form will be populated when GHL data is received -->
    </div>
  </div>

  <script>
    let paymentData = null;
    let isReady = false;
    let isSafari = false;
    let paymentPopup = null;
    let ghlAcknowledged = false;
    let readyEventRetryInterval = null;
    let readyEventRetryCount = 0;
    const MAX_RETRY_ATTEMPTS = 20;
    const RETRY_INTERVAL = 500;

    function isValidGHLMessage(data) {
      if (!data || typeof data !== 'object') {
        return false;
      }
      
      const validTypes = ['payment_initiate_props', 'setup_initiate_props'];
      if (!data.type || !validTypes.includes(data.type)) {
        return false;
      }
      
      if (data.type === 'payment_initiate_props') {
        return data.publishableKey && 
               data.amount && 
               data.currency && 
               data.mode && 
               data.orderId && 
               data.transactionId && 
               data.locationId;
      }
      
      if (data.type === 'setup_initiate_props') {
        return data.publishableKey && 
               data.currency && 
               data.mode === 'setup' && 
               data.contact && 
               data.contact.id && 
               data.locationId;
      }
      
      return true;
    }

    function isExtensionMessage(data) {
      if (!data || typeof data !== 'object') {
        return false;
      }
      
      if (data.__NG_DEVTOOLS_EVENT__ || 
          data.topic === 'handshake' || 
          data.topic === 'detectAngular' ||
          data.__ignore_ng_zone__ !== undefined ||
          data.isIvy !== undefined ||
          data.isAngular !== undefined) {
        return true;
      }
      
      if (data.source && (
          data.source.includes('extension') ||
          data.source.includes('devtools') ||
          data.source.includes('content-script') ||
          data.source.includes('angular') ||
          data.source.includes('CGK9cZpr.js')
        )) {
        return true;
      }
      
      if (data.type && (
          data.type.includes('extension') ||
          data.type.includes('devtools') ||
          data.type.includes('angular')
        )) {
        return true;
      }
      
      if (data.ready === true && !data.type) {
        return true;
      }
      
      if (data.args !== undefined && data.topic === 'handshake') {
        return true;
      }
      
      return false;
    }

    function isPotentialGHLMessage(data) {
      if (!data || typeof data !== 'object') {
        return false;
      }
      
      if (data.type === 'payment_initiate_props' || data.type === 'setup_initiate_props') {
        return true;
      }
      
      if (data.publishableKey && (data.amount || data.currency)) {
        return true;
      }
      
      if (data.type && (
          data.type.includes('payment') ||
          data.type.includes('setup') ||
          data.type.includes('custom_provider') ||
          data.type.includes('initiate')
        )) {
        return true;
      }
      
      if (data.orderId || data.transactionId || data.locationId) {
        return true;
      }
      
      if (data.contact && typeof data.contact === 'object') {
        return true;
      }
      
      return false;
    }

    window.addEventListener('message', function(event) {
      try {
        if (!event.data) {
          return;
        }
        
        if (isExtensionMessage(event.data)) {
          return;
        }
        
        if (event.data && typeof event.data === 'object') {
          const dataStr = JSON.stringify(event.data);
          if (dataStr.includes('CGK9cZpr.js') ||
              dataStr.includes('content_script') ||
              dataStr.includes('extension') ||
              dataStr.includes('devtools') ||
              dataStr.includes('angular') ||
              dataStr.includes('__NG_DEVTOOLS_EVENT__')) {
            return;
          }
        }
        
        let parsedData = event.data;
        if (typeof event.data === 'string') {
          try {
            parsedData = JSON.parse(event.data);
          } catch (e) {
            return;
          }
        }
        
        if (!isPotentialGHLMessage(parsedData)) {
          return;
        }
        
        if (!isValidGHLMessage(parsedData)) {
          return;
        }
        
        if (!ghlAcknowledged) {
          ghlAcknowledged = true;
          stopReadyEventRetry();
        }
        
        if (parsedData.type === 'payment_initiate_props') {
          paymentData = parsedData;
          updatePaymentForm(paymentData);
        } else if (parsedData.type === 'setup_initiate_props') {
          paymentData = parsedData;
          updatePaymentFormForSetup(paymentData);
        }
      } catch (error) {
        // Silent error handling
      }
    });

    window.addEventListener('message', function(event) {
      try {
        let parsedData = event.data;
        if (typeof event.data === 'string') {
          try {
            parsedData = JSON.parse(event.data);
          } catch (e) {
            return;
          }
        }
        
        if (parsedData && typeof parsedData === 'object' && 
            (parsedData.type === 'payment_initiate_props' || parsedData.type === 'setup_initiate_props')) {
          return;
        }
        
        if (parsedData && typeof parsedData === 'object' && 
            (parsedData.publishableKey || parsedData.amount || parsedData.orderId)) {
          
          if (parsedData.publishableKey && parsedData.amount && parsedData.currency) {
            if (!ghlAcknowledged) {
              ghlAcknowledged = true;
              stopReadyEventRetry();
            }
            
            const fallbackData = {
              type: 'payment_initiate_props',
              publishableKey: parsedData.publishableKey,
              amount: parsedData.amount,
              currency: parsedData.currency,
              mode: parsedData.mode || 'payment',
              orderId: parsedData.orderId || 'fallback_order_' + Date.now(),
              transactionId: parsedData.transactionId || 'fallback_txn_' + Date.now(),
              locationId: parsedData.locationId || 'fallback_loc_' + Date.now(),
              contact: parsedData.contact || null,
              productDetails: parsedData.productDetails || null
            };
            
            paymentData = fallbackData;
            updatePaymentForm(fallbackData);
          }
        }
      } catch (error) {
        // Silent error handling
      }
    });

    window.addEventListener('message', function(event) {
      try {
        let parsedData = event.data;
        if (typeof event.data === 'string') {
          try {
            parsedData = JSON.parse(event.data);
          } catch (e) {
            return;
          }
        }
        
        if (parsedData && typeof parsedData === 'object' && 
            (parsedData.type === 'payment_initiate_props' || parsedData.type === 'setup_initiate_props')) {
          return;
        }
        
        if (parsedData && typeof parsedData === 'object' && 
            (parsedData.type === 'custom_element_success_response' || 
             parsedData.type === 'custom_element_error_response' || 
             parsedData.type === 'custom_element_close_response')) {
          
          if (paymentPopup && !paymentPopup.closed) {
            try {
              paymentPopup.close();
              if (window.paymentPopupCheckInterval) {
                clearInterval(window.paymentPopupCheckInterval);
                window.paymentPopupCheckInterval = null;
              }
            } catch (e) {
              // Silent error handling
            }
            
            if (parsedData.type === 'custom_element_success_response') {
              sendSuccessResponse(parsedData.chargeId);
            } else if (parsedData.type === 'custom_element_error_response') {
              const errorMsg = parsedData.error?.description || 'Payment failed';
              sendErrorResponse(errorMsg);
            } else if (parsedData.type === 'custom_element_close_response') {
              sendCloseResponse();
            }
          }
        }
      } catch (error) {
        // Silent error handling
      }
    });

    window.addEventListener('storage', function(event) {
      try {
        if (event.key && event.key.startsWith('ghl_payment_message_') && event.newValue) {
          const messageData = JSON.parse(event.newValue);
          
          if (messageData.source === 'payment_redirect' && messageData.message) {
            const message = messageData.message;
            
            if (message.type === 'custom_element_success_response') {
              if (window.parent && window.parent !== window) {
                window.parent.postMessage(JSON.stringify(message), '*');
              }
              if (window.top && window.top !== window && window.top !== window.parent) {
                window.top.postMessage(JSON.stringify(message), '*');
              }
            } else if (message.type === 'custom_element_error_response') {
              if (window.parent && window.parent !== window) {
                window.parent.postMessage(JSON.stringify(message), '*');
              }
              if (window.top && window.top !== window && window.top !== window.parent) {
                window.top.postMessage(JSON.stringify(message), '*');
              }
            } else if (message.type === 'custom_element_close_response') {
              if (window.parent && window.parent !== window) {
                window.parent.postMessage(JSON.stringify(message), '*');
              }
              if (window.top && window.top !== window && window.top !== window.parent) {
                window.top.postMessage(JSON.stringify(message), '*');
              }
            }
          }
        }
      } catch (error) {
        // Silent error handling
      }
    });

    let localStoragePollInterval = null;
    const lastProcessedMessages = new Set();
    
    function pollLocalStorageMessages() {
      try {
        for (let i = 0; i < localStorage.length; i++) {
          const key = localStorage.key(i);
          if (key && key.startsWith('ghl_payment_message_') && !lastProcessedMessages.has(key)) {
            try {
              const messageDataStr = localStorage.getItem(key);
              if (messageDataStr) {
                const messageData = JSON.parse(messageDataStr);
                
                const messageAge = Date.now() - messageData.timestamp;
                if (messageAge < 5000 && messageData.source === 'payment_redirect' && messageData.message) {
                  lastProcessedMessages.add(key);
                  
                  const message = messageData.message;
                  if (message.type === 'custom_element_success_response' || 
                      message.type === 'custom_element_error_response' || 
                      message.type === 'custom_element_close_response') {
                    
                    if (window.parent && window.parent !== window) {
                      window.parent.postMessage(JSON.stringify(message), '*');
                    }
                    if (window.top && window.top !== window && window.top !== window.parent) {
                      window.top.postMessage(JSON.stringify(message), '*');
                    }
                  }
                  
                  localStorage.removeItem(key);
                } else if (messageAge >= 5000) {
                  localStorage.removeItem(key);
                  lastProcessedMessages.delete(key);
                }
              }
            } catch (e) {
              // Silent error handling
            }
          }
        }
      } catch (error) {
        // Silent error handling
      }
    }
    
    if (typeof document !== 'undefined' && document.visibilityState !== undefined) {
      function startPolling() {
        if (document.visibilityState === 'visible') {
          if (!localStoragePollInterval) {
            localStoragePollInterval = setInterval(pollLocalStorageMessages, 500);
          }
        } else {
          if (localStoragePollInterval) {
            clearInterval(localStoragePollInterval);
            localStoragePollInterval = null;
          }
        }
      }
      
      document.addEventListener('visibilitychange', startPolling);
      startPolling();
    }

    function sendReadyEvent(forceRetry = false) {
      if (ghlAcknowledged && !forceRetry) {
        return;
      }

      const readyEvent = {
        type: 'custom_provider_ready',
        loaded: true,
        addCardOnFileSupported: true,
        version: '1.0',
        capabilities: ['payment', 'setup'],
        supportedModes: ['payment', 'setup']
      };
      
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(readyEvent), '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(readyEvent), '*');
        }
        
        isReady = true;
        
        readyEventRetryCount++;
        
        if (readyEventRetryCount === 1 && !ghlAcknowledged) {
          startReadyEventRetry();
        }
        
        if (ghlAcknowledged) {
          stopReadyEventRetry();
        } else if (readyEventRetryCount >= MAX_RETRY_ATTEMPTS) {
          stopReadyEventRetry();
        }
        
      } catch (error) {
        isReady = true;
      }
    }

    function startReadyEventRetry() {
      if (readyEventRetryInterval) {
        return;
      }
      
      readyEventRetryInterval = setInterval(() => {
        if (!ghlAcknowledged && readyEventRetryCount < MAX_RETRY_ATTEMPTS) {
          sendReadyEvent(true);
        } else {
          stopReadyEventRetry();
        }
      }, RETRY_INTERVAL);
    }

    function stopReadyEventRetry() {
      if (readyEventRetryInterval) {
        clearInterval(readyEventRetryInterval);
        readyEventRetryInterval = null;
      }
    }

    function updatePaymentForm(data) {
      if (!data || typeof data !== 'object') {
        return;
      }
      
      const paymentContainer = document.querySelector('.payment-container');
      if (paymentContainer) {
        paymentContainer.style.display = 'none';
      }
      
      setTimeout(() => {
        createCharge();
      }, 0);
    }

    function updatePaymentFormForSetup(data) {
      if (!data || typeof data !== 'object') {
        return;
      }
      
      const amountDisplay = document.getElementById('amount-display');
      if (amountDisplay) {
        amountDisplay.textContent = 'Card Setup';
      }
      
      showSuccess('✅ Card setup data received from GoHighLevel successfully!');
      setTimeout(() => {
        hideMessages();
      }, 3000);
    }

    function sendSuccessResponse(chargeId) {
      const successEvent = {
        type: 'custom_element_success_response',
        chargeId: chargeId
      };
      
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(successEvent), '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(successEvent), '*');
        }
      } catch (error) {
        // Silent error handling
      }
    }

    function sendSetupSuccessResponse() {
      const successEvent = {
        type: 'custom_element_success_response'
      };
      
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(successEvent), '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(successEvent), '*');
        }
      } catch (error) {
        // Silent error handling
      }
    }

    function sendErrorResponse(errorMessage) {
      const errorEvent = {
        type: 'custom_element_error_response',
        error: {
          description: errorMessage
        }
      };
      
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(errorEvent), '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(errorEvent), '*');
        }
      } catch (error) {
        // Silent error handling
      }
    }

    function sendCloseResponse() {
      const closeEvent = {
        type: 'custom_element_close_response'
      };
      
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(closeEvent), '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(closeEvent), '*');
        }
      } catch (error) {
        // Silent error handling
      }
    }

    function showError(message) {
      const errorDiv = document.getElementById('error-message');
      if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
      }
      const successDiv = document.getElementById('success-message');
      if (successDiv) {
        successDiv.style.display = 'none';
      }
    }

    function showSuccess(message) {
      const successDiv = document.getElementById('success-message');
      if (successDiv) {
        successDiv.textContent = message;
        successDiv.style.display = 'block';
      }
      const errorDiv = document.getElementById('error-message');
      if (errorDiv) {
        errorDiv.style.display = 'none';
      }
    }

    function hideMessages() {
      const errorDiv = document.getElementById('error-message');
      if (errorDiv) {
        errorDiv.style.display = 'none';
      }
      const successDiv = document.getElementById('success-message');
      if (successDiv) {
        successDiv.style.display = 'none';
      }
    }

    function showButton() {
      if (isSafari) {
        const paymentContainer = document.getElementById('payment-container');
        if (paymentContainer) {
          paymentContainer.style.display = 'block';
        }
        
        const paymentBody = document.querySelector('.payment-body');
        if (paymentBody) {
          paymentBody.innerHTML = `
            <div class="popup-payment-content">
              <div class="error-message" id="error-message" style="display: block; margin-bottom: 20px;"></div>
              <button id="retry-btn" type="button" class="proceed-payment-button">
                <span class="button-text">Retry Payment</span>
              </button>
            </div>
          `;
          
          const retryBtn = document.getElementById('retry-btn');
          if (retryBtn) {
            retryBtn.addEventListener('click', () => {
              createCharge();
            });
          }
        }
      }
    }

    async function createCharge() {
      if (!paymentData) {
        showError('No payment data received from GoHighLevel. Please ensure the integration is properly configured.');
        return;
      }

      try {
        if (!paymentData.amount || !paymentData.currency) {
          throw new Error('Missing required payment data: amount or currency');
        }

        const merchantResponse = await fetch(`/api/merchant-id?locationId=${encodeURIComponent(paymentData.locationId)}`, {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          }
        });

        let merchantId;
        let tapMode;
        if (merchantResponse.ok) {
          const merchantData = await merchantResponse.json();
          if (merchantData.success && merchantData.merchant_id) {
            merchantId = merchantData.merchant_id;
            tapMode = merchantData.tap_mode;
          } else {
            throw new Error('Failed to get merchant_id: ' + (merchantData.message || 'Unknown error'));
          }
        } else {
          const errorData = await merchantResponse.json().catch(() => ({ message: 'Failed to get merchant_id' }));
          throw new Error('Failed to get merchant_id: ' + (errorData.message || 'Unknown error'));
        }

        const tapResponse = await fetch('/api/charge/create-tap', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify({
            merchant: {
              id: tapMode === 'live' ? merchantId : ''
            },
            amount: paymentData.amount,
            currency: paymentData.currency,
            customer_initiated: true,
            threeDSecure: true,
            save_card: false,
            description: `Payment for ${paymentData.productDetails?.productId || 'product'}`,
            metadata: {
              udf1: `Order: ${paymentData.orderId}`,
              udf2: `Transaction: ${paymentData.transactionId}`,
              udf3: `Location: ${paymentData.locationId}`
            },
            receipt: {
              email: false,
              sms: false
            },
            reference: {
              transaction: paymentData.transactionId,
              order: paymentData.orderId
            },
            customer: paymentData.contact ? {
              first_name: paymentData.contact.name?.split(' ')[0] || 'Customer',
              middle_name: '',
              last_name: paymentData.contact.name?.split(' ').slice(1).join(' ') || 'User',
              email: paymentData.contact.email || 'customer@example.com',
              phone: {
                country_code: 965,
                number: parseInt(paymentData.contact.contact?.replace(/\D/g, '') || '790000000')
              }
            } : {
              first_name: 'Customer',
              last_name: 'User',
              email: 'customer@example.com',
              phone: {
                country_code: 965,
                number: 790000000
              }
            },
            post: {
              url: window.location.origin + '/charge/webhook'
            },
            redirect: {
              url: window.location.origin + '/payment/redirect?locationId=' + encodeURIComponent(paymentData.locationId || '')
            }
          })
        });

        let result;
        try {
          result = await tapResponse.json();
        } catch (e) {
          const textResponse = await tapResponse.text();
          throw new Error('Invalid JSON response from server');
        }

        if (tapResponse.ok && result.success && result.charge) {
          if (result.charge.transaction?.url) {
            const isKnetPayment = isKnetPaymentMethod(result.charge);
            const currentSafariCheck = detectSafari();
            const shouldUsePopup = currentSafariCheck || isKnetPayment;
            
            if (shouldUsePopup) {
              const paymentContainer = document.querySelector('.payment-container');
              if (paymentContainer) {
                paymentContainer.style.display = 'block';
              }
              showProceedPaymentPopup(result.charge.transaction.url, isKnetPayment);
            } else {
              const newTab = window.open(result.charge.transaction.url, '_blank');
              if (!newTab) {
                window.location.href = result.charge.transaction.url;
              }
            }
          } else {
            showError('No checkout URL received from Tap');
            showButton();
          }
        } else {
          showError(result.message || 'Failed to create charge with Tap');
          showButton();
          sendErrorResponse(result.message || 'Failed to create charge with Tap');
        }
      } catch (error) {
        showError('An error occurred while creating the charge. Please try again.');
        showButton();
        sendErrorResponse(error.message || 'An error occurred while creating the charge');
      }
    }

    function isKnetPaymentMethod(charge) {
      try {
        if (charge.source) {
          const sourceId = charge.source.id || '';
          
          if (sourceId === 'src_all' || sourceId.toLowerCase() === 'src_all') {
            return true;
          }
          
          if (sourceId.toLowerCase().includes('knet') || 
              charge.source.type?.toLowerCase().includes('knet') ||
              charge.source.object?.toLowerCase().includes('knet')) {
            return true;
          }
        }
        
        if (charge.transaction?.url) {
          const url = charge.transaction.url.toLowerCase();
          
          if (url.includes('knet') || url.includes('redirect') || url.includes('external')) {
            return true;
          }
        }
        
        if (charge.metadata) {
          const metadataStr = JSON.stringify(charge.metadata).toLowerCase();
          
          if (metadataStr.includes('knet')) {
            return true;
          }
        }
        
        if (charge.transaction?.url) {
          const url = charge.transaction.url;
          const isTapHosted = url.includes('tap.company') || url.includes('tap-payments.com');
          
          if (!isTapHosted) {
            return true;
          }
        }
        
        return false;
      } catch (error) {
        return false;
      }
    }

    function detectSafari() {
      const userAgent = navigator.userAgent;
      
      if (/Chrome/.test(userAgent) && !/Chromium/.test(userAgent)) {
        return false;
      }
      
      if (/Firefox/.test(userAgent)) {
        return false;
      }
      
      if (/Edg/.test(userAgent) || /Edge/.test(userAgent)) {
        return false;
      }
      
      if (/Opera/.test(userAgent) || /OPR/.test(userAgent)) {
        return false;
      }
      
      const isIOS = /iPad|iPhone|iPod/.test(userAgent);
      if (isIOS) {
        if (/Macintosh/.test(userAgent) && 'ontouchend' in document) {
          return true;
        }
        return true;
      }
      
      const isMac = /Macintosh/.test(userAgent);
      const hasSafari = /Safari/.test(userAgent);
      const noChrome = !/Chrome/.test(userAgent) && !/Chromium/.test(userAgent);
      
      if (isMac && hasSafari && noChrome) {
        return true;
      }
      
      if (/^((?!chrome|android).)*safari/i.test(userAgent)) {
        const isChrome = /Google Inc/.test(navigator.vendor);
        if (!isChrome) {
          return true;
        }
      }
      
      return false;
    }

    function showProceedPaymentPopup(url, isKnetPayment = false) {
      const finalSafariCheck = detectSafari();
      
      if (!finalSafariCheck && !isKnetPayment) {
        setTimeout(() => {
          window.location.href = url;
        }, 500);
        return;
      }
      
      const paymentBody = document.querySelector('.payment-body');
      const paymentContainer = document.querySelector('.payment-container');
      
      if (paymentBody && paymentContainer) {
        paymentBody.innerHTML = `
          <div class="popup-payment-content">
            <div class="loading-spinner-container">
              <div class="payment-loading-spinner"></div>
            </div>
            
            <button id="proceed-payment-btn" class="proceed-payment-button">
              <span class="button-arrow">→</span>
              <span class="button-text">الذهاب لصفحة الدفع</span>
            </button>
            
            <div class="payment-methods-logos">
            <img src="{{ asset('https://images.leadconnectorhq.com/image/f_webp/q_80/r_1200/u_https://assets.cdn.filesafe.space/xAN9Y8iZDOugbNvKBKad/media/6901e4a9a412c65d60fb7f4b.png') }}" alt="Tap" class="payment-logo">
          </div>
        `;
      }
      
      window.openPaymentUrl = url;
      
      const proceedBtn = document.getElementById('proceed-payment-btn');
      
      if (proceedBtn) {
        proceedBtn.onclick = function() {
          const popupFeatures = 'width=800,height=600,scrollbars=yes,resizable=yes,status=yes,location=yes,toolbar=no,menubar=no';
          
          paymentPopup = window.open(url, 'tap_payment', popupFeatures);
          
          if (!paymentPopup) {
            if (window.top && window.top !== window) {
              window.top.location.href = url;
            } else if (window.parent && window.parent !== window) {
              window.parent.location.href = url;
            } else {
              window.location.href = url;
            }
            return;
          }
          
          const checkClosed = setInterval(() => {
            if (paymentPopup.closed) {
              clearInterval(checkClosed);
              sendCloseResponse();
            }
          }, 1000);
          
          window.paymentPopupCheckInterval = checkClosed;
        };
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      isSafari = detectSafari();
      
      sendReadyEvent();

      document.addEventListener('visibilitychange', function() {
        if (!document.hidden && !ghlAcknowledged) {
          readyEventRetryCount = 0;
          sendReadyEvent();
        }
      });

      window.addEventListener('focus', function() {
        if (!ghlAcknowledged) {
          readyEventRetryCount = 0;
          sendReadyEvent();
        }
      });

      const createChargeBtn = document.getElementById('create-charge-btn');
      if (createChargeBtn) {
        createChargeBtn.addEventListener('click', createCharge);
      }

      document.addEventListener('keydown', (e) => {
        const createChargeBtn = document.getElementById('create-charge-btn');
        if (e.key === 'Enter' && createChargeBtn && !createChargeBtn.disabled) {
          createChargeBtn.click();
        }
      });
    });
  </script>
</body>
</html>
