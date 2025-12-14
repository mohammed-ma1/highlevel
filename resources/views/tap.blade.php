{{-- 
  resources/views/tap.blade.php
  
  GoHighLevel Payment Integration with Tap Payments
  
  The console will now show:
  - Only non-extension messages (filters out Angular DevTools noise)
  - Clear GHL message validation
  - Ready event confirmation
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name', 'Laravel') }} — Secure Payment</title>
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

    .payment-container {
      background: white;
      border-radius: 20px;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
      max-width: 480px;
      width: 100%;
      overflow: hidden;
      position: relative;
    }

    .payment-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 40px 30px;
      text-align: center;
      position: relative;
    }

    .payment-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="white" opacity="0.1"/><circle cx="75" cy="75" r="1" fill="white" opacity="0.1"/><circle cx="50" cy="10" r="0.5" fill="white" opacity="0.1"/><circle cx="10" cy="60" r="0.5" fill="white" opacity="0.1"/><circle cx="90" cy="40" r="0.5" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
      opacity: 0.3;
    }

    .payment-header h1 {
      font-size: 28px;
      font-weight: 700;
      margin-bottom: 8px;
      position: relative;
      z-index: 1;
    }

    .payment-header p {
      font-size: 16px;
      opacity: 0.9;
      font-weight: 400;
      position: relative;
      z-index: 1;
    }

    .payment-body {
      padding: 40px 30px;
    }

    .payment-amount {
      text-align: center;
      margin-bottom: 30px;
    }

    .amount-label {
      font-size: 14px;
      color: #6b7280;
      margin-bottom: 8px;
      font-weight: 500;
    }

    .amount-value {
      font-size: 32px;
      font-weight: 700;
      color: #1f2937;
    }

    .card-section {
      margin-bottom: 30px;
    }

    .section-title {
      font-size: 18px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .section-title i {
      color: #667eea;
    }

    #card-sdk-id {
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      padding: 20px;
      background: #fafafa;
      transition: all 0.3s ease;
      margin-bottom: 20px;
    }

    #card-sdk-id:focus-within {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      background: white;
    }

    .payment-button {
      width: 100%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      padding: 16px 24px;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      margin-bottom: 20px;
    }

    .payment-button:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
    }

    .payment-button:active {
      transform: translateY(0);
    }

    .payment-button:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
    }

    .loading-spinner {
      display: none;
      width: 20px;
      height: 20px;
      border: 2px solid transparent;
      border-top: 2px solid white;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin-right: 10px;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .result-section {
      margin-top: 20px;
    }

    .result-content {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
      font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
      font-size: 13px;
      line-height: 1.5;
      color: #2d3748;
      white-space: pre-wrap;
      word-break: break-all;
      max-height: 200px;
      overflow-y: auto;
      display: none;
    }

    .security-badges {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin-top: 20px;
      padding-top: 20px;
      border-top: 1px solid #e5e7eb;
    }

    .security-badge {
      display: flex;
      align-items: center;
      gap: 8px;
      color: #6b7280;
      font-size: 14px;
      font-weight: 500;
    }

    .security-badge i {
      color: #10b981;
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

    .success-message {
      background: #f0fdf4;
      border: 1px solid #bbf7d0;
      color: #16a34a;
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: none;
      font-size: 14px;
    }

    @media (max-width: 480px) {
      .payment-container {
        margin: 10px;
        border-radius: 16px;
      }
      
      .payment-header {
        padding: 30px 20px;
      }
      
      .payment-body {
        padding: 30px 20px;
      }
      
      .payment-header h1 {
        font-size: 24px;
      }
      
      .amount-value {
        font-size: 28px;
      }
    }
  </style>
</head>
<body>
  <div class="payment-container" id="payment-container" style="display: none;">
    <div class="payment-header">
      <h1><i class="fas fa-credit-card"></i> Secure Payment</h1>
      <p>Complete your transaction safely and securely</p>
    </div>
    
    <div class="payment-body">
      <div class="payment-amount">
        <div class="amount-label">Amount to Pay</div>
        <div class="amount-value" id="amount-display">1.00 JOD</div>
      </div>

      <div class="error-message" id="error-message"></div>
      <div class="success-message" id="success-message"></div>

      <div class="card-section">
        <div class="section-title">
          <i class="fas fa-credit-card"></i>
          Payment Information
        </div>
        
        <!-- Where the card UI will render -->
        <div id="card-sdk-id"></div>
      </div>

      <!-- Submit / Tokenize -->
      <button id="tap-tokenize-btn" type="button" class="payment-button">
        <div class="loading-spinner" id="loading-spinner"></div>
        <span id="button-text">Pay Now</span>
      </button>

      <!-- Result display -->
      <div class="result-section" id="result-section" style="display: none;">
        <div class="section-title">
          <i class="fas fa-receipt"></i>
          Transaction Details
        </div>
        <div id="tap-result" class="result-content"></div>
      </div>

      <div class="security-badges">
        <div class="security-badge">
          <i class="fas fa-shield-alt"></i>
          <span>SSL Secured</span>
        </div>
        <div class="security-badge">
          <i class="fas fa-lock"></i>
          <span>256-bit Encryption</span>
        </div>
        <div class="security-badge">
          <i class="fas fa-check-circle"></i>
          <span>PCI Compliant</span>
        </div>
      </div>
    </div>
  </div>

  <!-- Tap Web Card SDK v2 -->
  <script src="https://tap-sdks.b-cdn.net/card/1.0.2/index.js"></script>

  <script>
    // Global error handler to suppress extension-related errors
    window.addEventListener('error', function(event) {
      // Suppress errors from browser extensions
      if (event.filename && (
          event.filename.includes('chrome-extension://') ||
          event.filename.includes('moz-extension://') ||
          event.filename.includes('safari-extension://') ||
          event.filename.includes('content_script') ||
          event.filename.includes('Cr9l0Ika.js') ||
          event.filename.includes('ZsUpL8J-.js') ||
          event.filename.includes('detect-angular-for-extension-icon.ts')
        )) {
        event.preventDefault();
        return false;
      }
    });

    // Suppress unhandled promise rejections from extensions
    window.addEventListener('unhandledrejection', function(event) {
      if (event.reason && event.reason.message && (
          event.reason.message.includes('Unable to parse event message') ||
          event.reason.message.includes('extension') ||
          event.reason.message.includes('content_script')
        )) {
        event.preventDefault();
        return false;
      }
    });

    // Override console.error to filter extension messages
    const originalConsoleError = console.error;
    console.error = function(...args) {
      const message = args.join(' ');
      if (message.includes('Unable to parse event message') ||
          message.includes('ZsUpL8J-.js') ||
          message.includes('content_script') ||
          message.includes('angular-devtools')) {
        return; // Suppress extension-related console errors
      }
      originalConsoleError.apply(console, args);
    };

    // Pull SDK helpers
    const { renderTapCard, Theme, Currencies, Direction, Edges, Locale, tokenize } = window.CardSDK;

    // GoHighLevel iframe communication
    let paymentData = null;
    let isReady = false;
    let cardInitialized = false;

    // Debug logging helper
    function debugLog(category, message, data = null) {
      const timestamp = new Date().toISOString().substr(11, 12);
      const prefix = `[${timestamp}] [${category}]`;
      if (data) {
        console.log(`%c${prefix}%c ${message}`, 'color: #667eea; font-weight: bold', 'color: inherit', data);
      } else {
        console.log(`%c${prefix}%c ${message}`, 'color: #667eea; font-weight: bold', 'color: inherit');
      }
    }

    debugLog('INIT', '🚀 Payment page loaded, waiting for GHL data...');

    // Validate GHL message structure according to documentation
    function isValidGHLMessage(data) {
      // Handle string data
      if (typeof data === 'string') {
        try {
          data = JSON.parse(data);
        } catch (e) {
          return false;
        }
      }

      if (!data || typeof data !== 'object') {
        return false;
      }
      
      // Check for required GHL message properties
      const validTypes = ['payment_initiate_props', 'setup_initiate_props'];
      if (!data.type || !validTypes.includes(data.type)) {
        return false;
      }
      
      // For payment_initiate_props, check for required fields per GHL docs
      if (data.type === 'payment_initiate_props') {
        return data.publishableKey && 
               data.amount && 
               data.currency && 
               data.mode && 
               data.orderId && 
               data.transactionId && 
               data.locationId;
      }
      
      // For setup_initiate_props, check for required fields per GHL docs
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

    // Check if message is from Angular DevTools or other extensions
    function isExtensionMessage(data) {
      if (!data) {
        return false;
      }
      
      // Handle string data
      if (typeof data === 'string') {
        return false; // Let it be parsed first
      }
      
      if (typeof data !== 'object') {
        return false;
      }
      
      // Check for Angular DevTools specific properties
      if (data.__NG_DEVTOOLS_EVENT__ || 
          data.topic === 'handshake' || 
          data.topic === 'detectAngular' ||
          (data.source && data.source.includes('angular-devtools')) ||
          (data.source && data.source.includes('chrome-extension')) ||
          data.isIvy !== undefined ||
          data.isAngular !== undefined) {
        return true;
      }
      
      // Check for other common extension patterns
      if (data.source && (
          data.source.includes('extension') ||
          data.source.includes('devtools') ||
          data.source.includes('content-script') ||
          data.source.includes('angular-devtools-content-script')
        )) {
        return true;
      }
      
      // Check for browser extension message patterns
      if (data.type && (
          data.type.includes('extension') ||
          data.type.includes('devtools') ||
          data.type.includes('chrome')
        )) {
        return true;
      }
      
      // Check for Tap SDK messages that are not from GHL
      // Tap SDK sends messages with event property
      if (data.event && (data.event === 'onCardReady' || data.event === 'onFocus' || data.event === 'onBinIdentification')) {
        return true; // This is from Tap SDK, not GHL
      }
      
      // Check for generic ready events that aren't GHL-specific
      if (data.ready === true && !data.type) {
        return true;
      }
      
      // Check for extension-specific properties
      if (data.__ignore_ng_zone__ !== undefined ||
          data.args !== undefined && data.topic === 'handshake') {
        return true;
      }
      
      return false;
    }

    // Check if message looks like it could be from GHL
    function isPotentialGHLMessage(data) {
      if (!data) {
        return false;
      }
      
      // Try to parse if it's a string
      if (typeof data === 'string') {
        try {
          data = JSON.parse(data);
        } catch (e) {
          return false;
        }
      }
      
      if (typeof data !== 'object') {
        return false;
      }
      
      // GHL messages should have specific structure
      // Check for exact type match first (most reliable)
      if (data.type === 'payment_initiate_props' || data.type === 'setup_initiate_props') {
        return true;
      }
      
      // Check for GHL-like structure (has publishableKey and payment-related fields)
      if (data.publishableKey && (data.amount || data.currency || data.mode)) {
        return true;
      }
      
      // Check for type patterns that might be GHL
      if (data.type && (
          data.type.includes('payment') ||
          data.type.includes('setup') ||
          data.type.includes('custom_provider') ||
          data.type.includes('initiate')
        )) {
        return true;
      }
      
      // Check for GHL-specific fields
      if (data.orderId || data.transactionId || data.locationId) {
        return true;
      }
      
      // Check for contact object (GHL sends contact info)
      if (data.contact && typeof data.contact === 'object' && data.contact.id) {
        return true;
      }
      
      return false;
    }

      // Listen for messages from GoHighLevel parent window
      window.addEventListener('message', function(event) {
        try {
          // Parse string messages (GHL sometimes sends JSON strings)
          let parsedData = event.data;
          if (typeof event.data === 'string') {
            try {
              parsedData = JSON.parse(event.data);
            } catch (e) {
              return;
            }
          }
          
          // Skip extension messages (Angular DevTools, Chrome extensions, Tap SDK, etc.)
          if (isExtensionMessage(parsedData)) {
            return;
          }
          
          // Check if message could be from GHL
          if (!isPotentialGHLMessage(parsedData)) {
            debugLog('MSG', '⏭️ Skipping non-GHL message:', { type: parsedData?.type, origin: event.origin });
            return;
          }
          
          debugLog('MSG', '📥 Received potential GHL message from:', event.origin);
          debugLog('MSG', '📦 Message data:', parsedData);
          
          // Validate GHL message structure
          if (!isValidGHLMessage(parsedData)) {
            debugLog('MSG', '❌ Invalid GHL message structure - missing required fields');
            debugLog('MSG', '📋 Required fields check:', {
              type: parsedData?.type,
              publishableKey: !!parsedData?.publishableKey,
              amount: parsedData?.amount,
              currency: parsedData?.currency,
              mode: parsedData?.mode,
              orderId: parsedData?.orderId,
              transactionId: parsedData?.transactionId,
              locationId: parsedData?.locationId
            });
            return;
          }
          
          debugLog('MSG', '✅ Valid GHL message received!');
          
          // Process valid GHL payment events
          if (parsedData.type === 'payment_initiate_props') {
            debugLog('PAYMENT', '💳 Processing payment_initiate_props');
            debugLog('PAYMENT', '💰 Amount:', parsedData.amount + ' ' + parsedData.currency);
            debugLog('PAYMENT', '🔑 Publishable Key:', parsedData.publishableKey ? parsedData.publishableKey.substring(0, 15) + '...' : 'MISSING!');
            debugLog('PAYMENT', '📍 Location ID:', parsedData.locationId);
            debugLog('PAYMENT', '🆔 Order ID:', parsedData.orderId);
            debugLog('PAYMENT', '🔄 Transaction ID:', parsedData.transactionId);
            if (parsedData.contact) {
              debugLog('PAYMENT', '👤 Contact:', {
                name: parsedData.contact.name,
                email: parsedData.contact.email,
                id: parsedData.contact.id
              });
            }
            console.log('%c[PAYMENT DATA - FULL]', 'color: #10b981; font-weight: bold; font-size: 14px', parsedData);
            
            paymentData = parsedData;
            updatePaymentForm(paymentData);
          } else if (parsedData.type === 'setup_initiate_props') {
            debugLog('SETUP', '🔧 Processing setup_initiate_props (Add Card on File)');
            debugLog('SETUP', '🔑 Publishable Key:', parsedData.publishableKey ? parsedData.publishableKey.substring(0, 15) + '...' : 'MISSING!');
            console.log('%c[SETUP DATA - FULL]', 'color: #10b981; font-weight: bold; font-size: 14px', parsedData);
            
            paymentData = parsedData;
            updatePaymentFormForSetup(paymentData);
          }
        } catch (error) {
          debugLog('ERROR', '❌ Error processing message:', error);
        }
      
    });


    // Send ready event to GoHighLevel parent window (per GHL documentation)
    function sendReadyEvent() {
      const readyEvent = {
        type: 'custom_provider_ready',
        loaded: true,
        addCardOnFileSupported: true // We support adding cards on file per GHL docs
      };
      
      debugLog('SEND', '📤 Sending ready event to GHL parent window...', readyEvent);
      
      try {
        // Try to send to parent window
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(readyEvent, '*');
          debugLog('SEND', '✅ Ready event sent to parent window');
          
          // Also try with specific origin if we can detect it
          try {
            const parentOrigin = window.parent.location.origin;
            window.parent.postMessage(readyEvent, parentOrigin);
            debugLog('SEND', '✅ Ready event also sent with specific origin:', parentOrigin);
          } catch (e) {
            debugLog('SEND', '⚠️ Cannot access parent origin (cross-origin), using *');
          }
        } else {
          debugLog('SEND', '⚠️ No parent window detected - page may not be in iframe');
        }
        
        // Also try to send to top window if different from parent
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(readyEvent, '*');
          debugLog('SEND', '✅ Ready event also sent to top window');
        }
        
        isReady = true;
        debugLog('SEND', '✅ Ready state set to true');
      } catch (error) {
        debugLog('ERROR', '❌ Error sending ready event:', error);
        isReady = true;
      }
    }

    // Update payment form with data from GoHighLevel
    function updatePaymentForm(data) {
      // Validate required GHL data structure
      if (!data || typeof data !== 'object') {
        return;
      }
      
      // Show the payment container now that we have valid data
      const paymentContainer = document.getElementById('payment-container');
      if (paymentContainer) {
        paymentContainer.style.display = 'block';
      }
      
      // Update amount display
      if (data.amount && data.currency) {
        const amountDisplay = document.getElementById('amount-display');
        if (amountDisplay) {
          amountDisplay.textContent = data.amount + ' ' + data.currency;
        }
      }
      
      // Update customer info
      if (data.contact) {
        
        // Log customer details
        if (data.contact.name) {
        }
        if (data.contact.email) {
        }
        if (data.contact.contact) {
        }
        if (data.contact.id) {
        }
      }
      
      // Log additional GHL data
      if (data.orderId) {
      }
      if (data.transactionId) {
      }
      if (data.subscriptionId) {
      }
      if (data.locationId) {
      }
      if (data.mode) {
      }
      if (data.productDetails) {
      }
      
      // Initialize Tap card with the publishable key from GHL
      if (data.publishableKey && !cardInitialized) {
        debugLog('CARD', '🎴 Initializing Tap Card SDK with publishable key...');
        debugLog('CARD', '🔑 Key prefix:', data.publishableKey.substring(0, 15) + '...');
        initializeTapCard();
        cardInitialized = true;
        debugLog('CARD', '✅ Card initialization triggered');
      } else if (data.amount && data.currency && cardInitialized) {
        debugLog('CARD', '🔄 Updating card configuration with new amount:', data.amount + ' ' + data.currency);
        // Update transaction amount in Tap card configuration if card is already initialized
        try {
          window.CardSDK.updateCardConfiguration({
            transaction: {
              amount: data.amount,
              currency: data.currency
            }
          });
          debugLog('CARD', '✅ Card configuration updated');
        } catch (error) {
          debugLog('CARD', '⚠️ Card configuration update failed:', error);
        }
      } else if (!data.publishableKey) {
        debugLog('CARD', '❌ No publishable key in payment data - cannot initialize card!');
      } else if (cardInitialized) {
        debugLog('CARD', 'ℹ️ Card already initialized, skipping re-initialization');
      }
      
      // Show success message that GHL data was received
      showSuccess('✅ Payment data received from GoHighLevel successfully!');
      setTimeout(() => {
        hideMessages();
      }, 3000);
    }

    // Update payment form for setup (Add Card on File) flow
    function updatePaymentFormForSetup(data) {
      // Validate required setup data structure
      if (!data || typeof data !== 'object') {
        return;
      }
      
      // Show the payment container now that we have valid data
      const paymentContainer = document.getElementById('payment-container');
      if (paymentContainer) {
        paymentContainer.style.display = 'block';
      }
      
      // Update amount display for setup (no amount needed for card setup)
      const amountDisplay = document.getElementById('amount-display');
      if (amountDisplay) {
        amountDisplay.textContent = 'Card Setup';
      }
      
      // Update customer info
      if (data.contact) {
      }
      
      // Log additional GHL setup data
      if (data.locationId) {
      }
      if (data.mode) {
      }
      
      // Initialize Tap card with the publishable key from GHL for setup flow
      if (data.publishableKey && !cardInitialized) {
        initializeTapCard();
        cardInitialized = true;
      }
      
      // Show success message that GHL setup data was received
      showSuccess('✅ Card setup data received from GoHighLevel successfully!');
      setTimeout(() => {
        hideMessages();
      }, 3000);
    }

    // Send success response to GoHighLevel (per GHL documentation)
    function sendSuccessResponse(chargeId) {
      const successEvent = {
        type: 'custom_element_success_response',
        chargeId: chargeId // Payment gateway chargeId for given transaction
      };
      
      
      try {
        // Try to send to parent window
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(successEvent, '*');
        }
        
        // Also try to send to top window if different from parent
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(successEvent, '*');
        }
      } catch (error) {
      }
    }

    // Send setup success response to GoHighLevel (for card on file - per GHL documentation)
    function sendSetupSuccessResponse() {
      const successEvent = {
        type: 'custom_element_success_response'
        // No chargeId needed for setup success
      };
      
      
      try {
        // Try to send to parent window
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(successEvent, '*');
        }
        
        // Also try to send to top window if different from parent
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(successEvent, '*');
        }
      } catch (error) {
      }
    }

    // Send error response to GoHighLevel (per GHL documentation)
    function sendErrorResponse(errorMessage) {
      const errorEvent = {
        type: 'custom_element_error_response',
        error: {
          description: errorMessage // Error message to be shown to the user
        }
      };
      
      
      try {
        // Try to send to parent window
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(errorEvent, '*');
        }
        
        // Also try to send to top window if different from parent
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(errorEvent, '*');
        }
      } catch (error) {
      }
    }

    // Send close response to GoHighLevel (per GHL documentation)
    function sendCloseResponse() {
      const closeEvent = {
        type: 'custom_element_close_response'
      };
      
      
      try {
        // Try to send to parent window
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(closeEvent, '*');
        }
        
        // Also try to send to top window if different from parent
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(closeEvent, '*');
        }
      } catch (error) {
      }
    }

    // UI Helper functions
    function showLoading() {
      document.getElementById('loading-spinner').style.display = 'inline-block';
      document.getElementById('button-text').textContent = 'Processing...';
      document.getElementById('tap-tokenize-btn').disabled = true;
    }

    function hideLoading() {
      document.getElementById('loading-spinner').style.display = 'none';
      document.getElementById('button-text').textContent = 'Pay Now';
      document.getElementById('tap-tokenize-btn').disabled = false;
    }

    function showError(message) {
      const errorDiv = document.getElementById('error-message');
      errorDiv.textContent = message;
      errorDiv.style.display = 'block';
      document.getElementById('success-message').style.display = 'none';
    }

    function showSuccess(message) {
      const successDiv = document.getElementById('success-message');
      successDiv.textContent = message;
      successDiv.style.display = 'block';
      document.getElementById('error-message').style.display = 'none';
    }

    function hideMessages() {
      document.getElementById('error-message').style.display = 'none';
      document.getElementById('success-message').style.display = 'none';
    }

    function showResult(data) {
      const resultSection = document.getElementById('result-section');
      const resultContent = document.getElementById('tap-result');
      
      resultContent.textContent = 'Transaction Token:\n' + JSON.stringify(data, null, 2);
      resultSection.style.display = 'block';
      
      // Scroll to result
      resultSection.scrollIntoView({ behavior: 'smooth' });
    }

    // 1) Render the card
    function initializeTapCard() {
      const cardConfig = {
        publicKey: paymentData?.publishableKey || '',
        merchant: {
          id: ''
        },
        transaction: {
          amount: paymentData?.amount || 1,
          currency: paymentData?.currency || Currencies.JOD
        }
      };
      
      debugLog('CARD-SDK', '🎴 Initializing Tap Card with config:');
      debugLog('CARD-SDK', '🔑 Public Key:', cardConfig.publicKey ? cardConfig.publicKey.substring(0, 20) + '...' : 'EMPTY!');
      debugLog('CARD-SDK', '💰 Transaction:', cardConfig.transaction);
      
      if (!cardConfig.publicKey) {
        debugLog('CARD-SDK', '❌ ERROR: No public key provided! Card will fail to authenticate.');
        console.error('%c[CRITICAL] No publishable key - Tap SDK will return 401 Unauthorized!', 'color: red; font-weight: bold; font-size: 14px');
      }
      
      const { unmount } = renderTapCard('card-sdk-id', {
        publicKey: cardConfig.publicKey,
        merchant: cardConfig.merchant,
        transaction: cardConfig.transaction,
      // Optional but recommended customer info
      customer: {
        // id: 'cus_xxxxx',               // If you have a Tap customer ID
        name: [
          { lang: Locale.EN, first: '', last: '' }
        ],
        nameOnCard: '',
        editable: true,
        contact: {
          email: '',
          phone: { countryCode: '962', number: '' }
        }
      },
      // Show only the brands you're enabled for in your Tap account
      acceptance: {
        supportedBrands: ['VISA', 'MASTERCARD', 'AMERICAN_EXPRESS', 'MADA'],
        supportedCards: "ALL" // "ALL" | ["DEBIT"] | ["CREDIT"]
      },
      fields: {
        cardHolder: true
      },
      addons: {
        displayPaymentBrands: true,
        loader: true,
        saveCard: true
      },
      interface: {
        locale: Locale.EN,
        theme: Theme.LIGHT,               // LIGHT | DARK
        edges: Edges.CURVED,              // SHARP | CURVED
        direction: Direction.LTR
      },

      // Enhanced callbacks with better UX
      onReady: () => {
        debugLog('CARD-SDK', '✅ Tap Card SDK is READY!');
        hideMessages();
      },
      onFocus: () => {
        debugLog('CARD-SDK', '📝 Card input focused');
        hideMessages();
      },
      onBinIdentification: data => {
        debugLog('CARD-SDK', '💳 BIN identified:', data);
        // Clear any previous errors when BIN is identified
        hideMessages();
      },
      onValidInput: data => {
        hideMessages();
      },
      onInvalidInput: data => {
        // Show specific validation errors
        if (data.cardNumber && data.cardNumber.invalid) {
          showError('Please enter a valid card number (16 digits)');
        } else if (data.expiry && data.expiry.invalid) {
          showError('Please enter a valid expiry date (MM/YY)');
        } else if (data.cvv && data.cvv.invalid) {
          showError('Please enter a valid CVV (3-4 digits)');
        } else if (data.cardHolder && data.cardHolder.invalid) {
          showError('Please enter a valid cardholder name');
        } else {
          showError('Please check your card information and try again.');
        }
      },
      onError: err => {
        debugLog('CARD-SDK', '❌ Card SDK Error:', err);
        console.error('%c[CARD SDK ERROR]', 'color: red; font-weight: bold', err);
        showError('An error occurred while processing your card. Please try again.');
        hideLoading();
      },

      // When tokenization succeeds, you'll get the Tap Token here
      onSuccess: (data) => {
        debugLog('CARD-SDK', '✅ Tokenization SUCCESS!', data);
        console.log('%c[TOKENIZATION SUCCESS]', 'color: green; font-weight: bold; font-size: 14px', data);
        hideLoading();
        
        // Check if this is a setup flow (add card on file) or payment flow
        if (paymentData && paymentData.type === 'setup_initiate_props') {
          showSuccess('🎉 Card added successfully! Token: ' + data.id);
          showResult(data);
          
          // Send setup success response to GoHighLevel (no chargeId needed)
          sendSetupSuccessResponse();
        } else {
          showSuccess('🎉 Payment tokenized successfully! Token: ' + data.id);
          showResult(data);
          
          // Send payment success response to GoHighLevel with chargeId
          sendSuccessResponse(data.id);
        }

        // Example: POST token to your Laravel backend for creating a charge or saving card
        // fetch("{{ route('client.webhook') }}", {
        //   method: "POST",
        //   headers: {
        //     "Content-Type": "application/json",
        //     "X-CSRF-TOKEN": "{{ csrf_token() }}"
        //   },
        //   body: JSON.stringify({ 
        //     token: data?.id,
        //     type: paymentData?.type || 'payment_initiate_props',
        //     paymentData: paymentData
        //   })
      }
    });
    }

    // Don't initialize Tap Card immediately - wait for GHL to send payment data with publishableKey
    // The card will be initialized in updatePaymentForm() or updatePaymentFormForSetup()
    // when we receive valid payment data from GoHighLevel

    debugLog('INIT', '📍 Page context:', {
      isInIframe: window.parent !== window,
      hasParent: !!window.parent,
      hasTop: !!window.top,
      currentUrl: window.location.href
    });

    // Send ready event after a short delay to notify GHL we're ready to receive payment data
    debugLog('INIT', '⏳ Waiting 500ms before sending ready event...');
    setTimeout(() => {
      debugLog('INIT', '📤 Now sending ready event to GHL...');
      sendReadyEvent();
    }, 500);

    // Also send ready event immediately in case GHL is already listening
    debugLog('INIT', '📤 Sending immediate ready event...');
    sendReadyEvent();

    // 2) Wire the button to call tokenize()
    document.getElementById('tap-tokenize-btn').addEventListener('click', () => {
      hideMessages();
      showLoading();
      
      // Triggers SDK to validate inputs & create Tap token
      try {
        tokenize();
      } catch (error) {
        showError('Failed to process payment. Please try again.');
        hideLoading();
      }
    });

    // Add keyboard support
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !document.getElementById('tap-tokenize-btn').disabled) {
        document.getElementById('tap-tokenize-btn').click();
      }
    });
  </script>
</body>
</html>
