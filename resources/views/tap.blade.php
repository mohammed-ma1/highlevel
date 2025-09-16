{{-- 
  resources/views/tap.blade.php
  
  GoHighLevel Payment Integration with Tap Payments
  
  Debugging Features:
  - Check iframe context: window.testGHLIntegration.checkContext()
  - Test payment simulation: window.testGHLIntegration.simulatePayment()
  - Test complete flow: window.testGHLIntegration.testFlow()
  
  The console will now show:
  - Only non-extension messages (filters out Angular DevTools noise)
  - Iframe context information
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
  <div class="payment-container">
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

    // Validate GHL message structure according to documentation
    function isValidGHLMessage(data) {
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
      if (!data || typeof data !== 'object') {
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
      if (data.event === 'onCardReady' && data.data && data.data.ready === true) {
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
      if (!data || typeof data !== 'object') {
        return false;
      }
      
      // GHL messages should have specific structure
      return data.type === 'payment_initiate_props' || 
             data.type === 'setup_initiate_props' ||
             (data.publishableKey && data.amount && data.currency) ||
             (data.type && data.type.includes('payment')) ||
             (data.type && data.type.includes('setup')) ||
             (data.type && data.type.includes('custom_provider'));
    }

      // Listen for messages from GoHighLevel parent window
      window.addEventListener('message', function(event) {
        // Log all messages for debugging (you can remove this later)
        try {
        // Skip extension messages (Angular DevTools, Chrome extensions, Tap SDK, etc.)
        if (isExtensionMessage(event.data)) {
          // Silently skip extension messages - no need to log them
          return;
        }
        
        // Only log messages that could potentially be from GHL
        if (isPotentialGHLMessage(event.data)) {
          // console.log('🔍 Received potential GHL message:', {
          //   origin: event.origin,
          //   type: event.data?.type,
          //   hasAmount: !!event.data?.amount,
          //   hasCurrency: !!event.data?.currency,
          //   hasPublishableKey: !!event.data?.publishableKey,
          //   fullData: event.data,
          //   timestamp: new Date().toISOString()
          // });
        } else {
          // Log non-GHL messages at debug level only
          console.debug('🔍 Received non-GHL message (ignored):', {
            origin: event.origin,
            type: event.data?.type,
            reason: 'Not a GHL message format'
          });
          return;
        }
        
        // Validate GHL message structure
        if (!isValidGHLMessage(event.data)) {
          console.log('❌ Invalid GHL message structure:', {
            received: event.data,
            expected: 'Should have type: payment_initiate_props or setup_initiate_props'
          });
          return;
        }
        
        // Process valid GHL payment events
        if (event.data.type === 'payment_initiate_props') {
          paymentData = event.data;
          console.log('✅ GHL Payment data received:', paymentData);
          console.log('💰 Amount from GoHighLevel:', paymentData.amount, paymentData.currency);
          console.log('🔑 Publishable Key:', paymentData.publishableKey);
          console.log('👤 Customer Info:', paymentData.contact);
          console.log('📋 Order ID:', paymentData.orderId);
          console.log('🏢 Location ID:', paymentData.locationId);
          console.log('🎯 Payment Mode:', paymentData.mode);
          console.log('📦 Product Details:', paymentData.productDetails);
          updatePaymentForm(paymentData);
        } else if (event.data.type === 'setup_initiate_props') {
          paymentData = event.data;
          console.log('✅ GHL Setup data received (Add Card on File):', paymentData);
          console.log('🔑 Publishable Key:', paymentData.publishableKey);
          console.log('👤 Customer ID:', paymentData.contact.id);
          console.log('🏢 Location ID:', paymentData.locationId);
          console.log('🎯 Setup Mode:', paymentData.mode);
          updatePaymentFormForSetup(paymentData);
        }
      } catch (error) {
        console.error('❌ Error processing message from parent:', error);
        // Ignore parsing errors from other extensions or scripts
      }
    });

    // Add debugging function to check if we're in an iframe
    function checkIframeContext() {
      console.log('🔍 Iframe Context Check:');
      console.log('- In iframe:', window !== window.top);
      console.log('- Current origin:', window.location.origin);
      console.log('- Current URL:', window.location.href);
      
      try {
        console.log('- Parent origin:', window.parent.location?.origin || 'Cannot access parent origin');
        console.log('- Parent URL:', window.parent.location?.href || 'Cannot access parent URL');
      } catch (e) {
        console.log('ℹ️ Parent access blocked (cross-origin) - This is NORMAL and EXPECTED:', e.message);
        console.log('ℹ️ Cross-origin restrictions are a browser security feature, not an error');
      }
      
      // Check if we can communicate with parent
      try {
        window.parent.postMessage({ type: 'test_communication' }, '*');
        console.log('✅ Can send messages to parent');
      } catch (e) {
        console.log('❌ Cannot send messages to parent:', e.message);
      }
    }

    // Send ready event to GoHighLevel parent window (per GHL documentation)
    function sendReadyEvent() {
      const readyEvent = {
        type: 'custom_provider_ready',
        loaded: true,
        addCardOnFileSupported: true // We support adding cards on file per GHL docs
      };
      
      console.log('📤 Sending ready event to GHL:', readyEvent);
      
      try {
        // Try to send to parent window
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(readyEvent, '*');
        }
        
        // Also try to send to top window if different from parent
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(readyEvent, '*');
        }
        
        isReady = true;
        console.log('✅ Payment iframe is ready and listening for GHL messages');
      } catch (error) {
        console.warn('⚠️ Could not send ready event to parent:', error.message);
        // Still mark as ready even if we can't communicate with parent
        isReady = true;
      }
    }

    // Update payment form with data from GoHighLevel
    function updatePaymentForm(data) {
      console.log('🔄 Updating payment form with GHL data:', data);
      
      // Validate required GHL data structure
      if (!data || typeof data !== 'object') {
        console.error('❌ Invalid payment data received');
        return;
      }
      
      // Update amount display
      if (data.amount && data.currency) {
        const amountDisplay = document.getElementById('amount-display');
        if (amountDisplay) {
          amountDisplay.textContent = data.amount + ' ' + data.currency;
          console.log('💰 Amount updated to:', data.amount + ' ' + data.currency);
        }
      }
      
      // Update customer info
      if (data.contact) {
        console.log('👤 Customer info received:', data.contact);
        
        // Log customer details
        if (data.contact.name) {
          console.log('👤 Customer name:', data.contact.name);
        }
        if (data.contact.email) {
          console.log('📧 Customer email:', data.contact.email);
        }
        if (data.contact.contact) {
          console.log('📞 Customer phone:', data.contact.contact);
        }
        if (data.contact.id) {
          console.log('🆔 Customer ID:', data.contact.id);
        }
      }
      
      // Log additional GHL data
      if (data.orderId) {
        console.log('📋 Order ID:', data.orderId);
      }
      if (data.transactionId) {
        console.log('💳 Transaction ID:', data.transactionId);
      }
      if (data.subscriptionId) {
        console.log('🔄 Subscription ID:', data.subscriptionId);
      }
      if (data.locationId) {
        console.log('🏢 Location ID:', data.locationId);
      }
      if (data.mode) {
        console.log('🎯 Payment mode:', data.mode);
      }
      if (data.productDetails) {
        console.log('📦 Product details:', data.productDetails);
      }
      
      // Update publishable key if provided
      if (data.publishableKey) {
        console.log('🔑 New publishable key received:', data.publishableKey);
        // Note: To fully update the publishable key, we would need to reinitialize the Tap card
        // This is complex and may require unmounting and remounting the entire card component
      }
      
      // Update transaction amount in Tap card configuration
      if (data.amount && data.currency) {
        // Update the Tap card configuration with new amount
        try {
          window.CardSDK.updateCardConfiguration({
            transaction: {
              amount: data.amount,
              currency: data.currency
            }
          });
          console.log('✅ Tap card configuration updated with amount:', data.amount, 'currency:', data.currency);
        } catch (error) {
          console.log('⚠️ Could not update Tap card configuration:', error);
        }
      }
      
      // Show success message that GHL data was received
      showSuccess('✅ Payment data received from GoHighLevel successfully!');
      setTimeout(() => {
        hideMessages();
      }, 3000);
    }

    // Update payment form for setup (Add Card on File) flow
    function updatePaymentFormForSetup(data) {
      console.log('🔄 Updating payment form for setup with GHL data:', data);
      
      // Validate required setup data structure
      if (!data || typeof data !== 'object') {
        console.error('❌ Invalid setup data received');
        return;
      }
      
      // Update amount display for setup (no amount needed for card setup)
      const amountDisplay = document.getElementById('amount-display');
      if (amountDisplay) {
        amountDisplay.textContent = 'Card Setup';
        console.log('💳 Setup mode: Adding card on file');
      }
      
      // Update customer info
      if (data.contact) {
        console.log('👤 Customer info for setup:', data.contact);
        console.log('🆔 Customer ID:', data.contact.id);
      }
      
      // Log additional GHL setup data
      if (data.locationId) {
        console.log('🏢 Location ID:', data.locationId);
      }
      if (data.mode) {
        console.log('🎯 Setup mode:', data.mode);
      }
      
      // Update publishable key if provided
      if (data.publishableKey) {
        console.log('🔑 New publishable key received for setup:', data.publishableKey);
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
      
      console.log('✅ Sending success response to GHL:', successEvent);
      
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
        console.warn('⚠️ Could not send success response to parent:', error.message);
      }
    }

    // Send setup success response to GoHighLevel (for card on file - per GHL documentation)
    function sendSetupSuccessResponse() {
      const successEvent = {
        type: 'custom_element_success_response'
        // No chargeId needed for setup success
      };
      
      console.log('✅ Sending setup success response to GHL:', successEvent);
      
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
        console.warn('⚠️ Could not send setup success response to parent:', error.message);
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
      
      console.log('❌ Sending error response to GHL:', errorEvent);
      
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
        console.warn('⚠️ Could not send error response to parent:', error.message);
      }
    }

    // Send close response to GoHighLevel (per GHL documentation)
    function sendCloseResponse() {
      const closeEvent = {
        type: 'custom_element_close_response'
      };
      
      console.log('Sending close response:', closeEvent);
      
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
        console.warn('⚠️ Could not send close response to parent:', error.message);
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
      const { unmount } = renderTapCard('card-sdk-id', {
        publicKey: paymentData?.publishableKey || '{{ $publishableKey ?? "pk_test_YhUjg9PNT8oDlKJ1aE2fMRz7" }}',
        merchant: {
          id: '{{ $merchantId ?? "merchant_id_here" }}'
        },
        transaction: {
          amount: paymentData?.amount || 1,
          currency: paymentData?.currency || Currencies.JOD
        },
      // Optional but recommended customer info
      customer: {
        // id: 'cus_xxxxx',               // If you have a Tap customer ID
        name: [
          { lang: Locale.EN, first: 'Test', last: 'User' }
        ],
        nameOnCard: 'Test User',
        editable: true,
        contact: {
          email: 'test@example.com',
          phone: { countryCode: '962', number: '790000000' }
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
        console.log('Tap Card: ready');
        hideMessages();
      },
      onFocus: () => {
        console.log('Tap Card: focus');
        hideMessages();
      },
      onBinIdentification: data => {
        console.log('BIN identified:', data);
        // Clear any previous errors when BIN is identified
        hideMessages();
      },
      onValidInput: data => {
        console.log('Valid input:', data);
        hideMessages();
      },
      onInvalidInput: data => {
        console.log('Invalid input details:', JSON.stringify(data, null, 2));
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
        console.error('Tap Card error:', err);
        showError('An error occurred while processing your card. Please try again.');
        hideLoading();
      },

      // When tokenization succeeds, you'll get the Tap Token here
      onSuccess: (data) => {
        console.log('Token success:', data);
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
        // }).then(r => r.json()).then(console.log).catch(console.error);
      }
    });
    }

    // Initialize Tap Card when page loads
    initializeTapCard();

    // Check iframe context and send ready event after a short delay
    setTimeout(() => {
      checkIframeContext();
      sendReadyEvent();
      
      // Set a timeout to check if GHL sent payment data
      setTimeout(() => {
        if (!paymentData) {
          console.log('⚠️ WARNING: No payment data received from GHL after 5 seconds');
          console.log('🔍 This could mean:');
          console.log('  1. GHL integration not configured properly');
          console.log('  2. Payment URL not set correctly in GHL');
          console.log('  3. API keys not configured in GHL');
          console.log('  4. Integration not activated in GHL');
          console.log('  5. Testing in wrong GHL environment');
          console.log('');
          console.log('🧪 To test your iframe, run: window.testGHLIntegration.testGHLFlow()');
        }
      }, 5000);
    }, 1000);

    // Add a console message to help with debugging
    console.log('🚀 Tap Payment Integration Loaded Successfully');
    console.log('📋 Available test functions:');
    console.log('  - window.testGHLIntegration.testCompleteFlow() - Test complete payment flow with backend verification');
    console.log('  - window.testGHLIntegration.testGHLFlow() - Test exact GHL documentation flow');
    console.log('  - window.testGHLIntegration.simulatePayment() - Test with mock GHL payment data');
    console.log('  - window.testGHLIntegration.simulateSetup() - Test with mock GHL setup data (add card)');
    console.log('  - window.testGHLIntegration.testFlow() - Test basic payment flow');
    console.log('  - window.testGHLIntegration.checkContext() - Check iframe context');
    console.log('');
    console.log('🔍 DEBUGGING GHL INTEGRATION:');
    console.log('  - If you see "✅ GHL Payment data received" = GHL is working correctly');
    console.log('  - If you only see Tap SDK messages = GHL is not sending payment data');
    console.log('  - Check GHL integration configuration if no payment data received');
    console.log('');
    console.log('ℹ️ NOTE: Cross-origin iframe restrictions are NORMAL and EXPECTED');
    console.log('ℹ️ The "Parent access blocked" message is a browser security feature, not an error');
    console.log('ℹ️ Communication with GHL works via postMessage, not direct property access');
    console.log('ℹ️ Extension errors (Angular DevTools, etc.) are now suppressed for cleaner console');
    console.log('ℹ️ Only GHL-related messages will be shown in the console');

    // Debug function to simulate GHL payment data (for testing - matches GHL docs exactly)
    function simulateGHLPaymentData() {
      const testData = {
        type: 'payment_initiate_props',
        publishableKey: 'pk_test_YhUjg9PNT8oDlKJ1aE2fMRz7',
        amount: 25.50,
        currency: 'JOD',
        mode: 'payment',
        productDetails: {
          productId: 'prod_test_123',
          priceId: 'price_test_456'
        },
        contact: {
          id: 'cus_test_789',
          name: 'John Doe',
          email: 'john.doe@example.com',
          contact: '+962790000000'
        },
        orderId: 'order_test_101112',
        transactionId: 'txn_test_131415',
        subscriptionId: 'sub_test_161718',
        locationId: 'loc_test_161718'
      };
      
      console.log('🧪 Simulating GHL payment data for testing:', testData);
      updatePaymentForm(testData);
    }

    // Add function to test the complete payment flow
    function testPaymentFlow() {
      console.log('🧪 Testing complete payment flow...');
      
      // Simulate receiving GHL data
      simulateGHLPaymentData();
      
      // Wait a bit then simulate successful payment
      setTimeout(() => {
        console.log('🧪 Simulating successful payment tokenization...');
        const mockTokenData = {
          id: 'tok_test_123456789',
          object: 'token',
          created: Date.now(),
          used: false,
          type: 'card'
        };
        
        console.log('✅ Mock token received:', mockTokenData);
        showSuccess('🎉 Test payment tokenized successfully! Token: ' + mockTokenData.id);
        showResult(mockTokenData);
        
        // Send success response to GHL
        sendSuccessResponse(mockTokenData.id);
      }, 3000);
    }

    // Debug function to simulate GHL setup data (for testing - matches GHL docs exactly)
    function simulateGHLSetupData() {
      const testData = {
        type: 'setup_initiate_props',
        publishableKey: 'pk_test_YhUjg9PNT8oDlKJ1aE2fMRz7',
        currency: 'JOD',
        mode: 'setup',
        contact: {
          id: 'cus_test_789'
        },
        locationId: 'loc_test_161718'
      };
      
      console.log('🧪 Simulating GHL setup data for testing:', testData);
      updatePaymentFormForSetup(testData);
    }

    // Test function to simulate the exact GHL flow from the documentation
    function testGHLDocumentationFlow() {
      console.log('🧪 Testing GHL Documentation Flow...');
      console.log('📋 Step 1: Iframe sends ready event (already done)');
      console.log('📋 Step 2: Simulating GHL sending payment data...');
      
      // Simulate the exact data structure from GHL documentation
      const ghlPaymentData = {
        type: 'payment_initiate_props',
        publishableKey: 'pk_test_YhUjg9PNT8oDlKJ1aE2fMRz7',
        amount: 25.50,
        currency: 'JOD',
        mode: 'payment',
        productDetails: {
          productId: 'prod_123456',
          priceId: 'price_789012'
        },
        contact: {
          id: 'cus_345678',
          name: 'John Doe',
          email: 'john.doe@example.com',
          contact: '+962790000000'
        },
        orderId: 'order_901234',
        transactionId: 'txn_567890',
        subscriptionId: 'sub_123456',
        locationId: 'loc_789012'
      };
      
      console.log('📤 Simulating GHL sending this data:', ghlPaymentData);
      
      // Simulate receiving the message
      setTimeout(() => {
        console.log('📥 Processing GHL payment data...');
        updatePaymentForm(ghlPaymentData);
      }, 1000);
    }

    // Test function to simulate the complete payment flow including backend verification
    function testCompletePaymentFlow() {
      console.log('🧪 Testing Complete Payment Flow...');
      console.log('📋 Step 1: Simulate GHL payment data');
      
      // Simulate GHL payment data
      const ghlPaymentData = {
        type: 'payment_initiate_props',
        publishableKey: 'pk_test_YhUjg9PNT8oDlKJ1aE2fMRz7',
        amount: 25.50,
        currency: 'JOD',
        mode: 'payment',
        contact: {
          id: 'cus_345678',
          name: 'John Doe',
          email: 'john.doe@example.com'
        },
        orderId: 'order_901234',
        transactionId: 'txn_567890',
        locationId: 'loc_789012'
      };
      
      updatePaymentForm(ghlPaymentData);
      
      // Simulate successful payment after 3 seconds
      setTimeout(() => {
        console.log('📋 Step 2: Simulating successful payment...');
        const mockTokenData = {
          id: 'tok_test_123456789',
          object: 'token',
          created: Date.now(),
          used: false,
          type: 'card'
        };
        
        console.log('✅ Mock token received:', mockTokenData);
        showSuccess('🎉 Test payment tokenized successfully! Token: ' + mockTokenData.id);
        showResult(mockTokenData);
        
        // Send success response to GHL
        sendSuccessResponse(mockTokenData.id);
        
        // Simulate backend verification
        setTimeout(() => {
          console.log('📋 Step 3: Simulating backend verification...');
          console.log('🔗 GHL would call: POST /payment/query');
          console.log('📤 With payload:', {
            type: 'verify',
            transactionId: 'txn_567890',
            apiKey: 'test_api_key',
            chargeId: 'tok_test_123456789',
            subscriptionId: 'sub_123456'
          });
          console.log('✅ Backend would respond: { success: true }');
          console.log('🎉 User would be redirected to success page');
        }, 2000);
        
      }, 3000);
    }

    // Make test functions available globally for debugging
    window.testGHLIntegration = {
      simulatePayment: simulateGHLPaymentData,
      simulateSetup: simulateGHLSetupData,
      testFlow: testPaymentFlow,
      testGHLFlow: testGHLDocumentationFlow,
      testCompleteFlow: testCompletePaymentFlow,
      checkContext: checkIframeContext
    };

    // Also make functions available on parent window for cross-frame access
    try {
      if (window.parent && window.parent !== window) {
        window.parent.testGHLIntegration = window.testGHLIntegration;
        console.log('✅ Test functions also available on parent window as window.testGHLIntegration');
      }
    } catch (e) {
      console.log('ℹ️ Cannot access parent window (cross-origin restriction)');
    }

    // Uncomment the line below to test with simulated data
    // setTimeout(simulateGHLPaymentData, 2000);

    // Update amount display if we have payment data
    if (paymentData && paymentData.amount && paymentData.currency) {
      updatePaymentForm(paymentData);
    }

    // 2) Wire the button to call tokenize()
    document.getElementById('tap-tokenize-btn').addEventListener('click', () => {
      hideMessages();
      showLoading();
      
      // Triggers SDK to validate inputs & create Tap token
      try {
        tokenize();
      } catch (error) {
        console.error('Tokenization error:', error);
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
