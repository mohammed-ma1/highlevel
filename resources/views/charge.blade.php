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

    .payment-section {
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

    .processing-message {
      text-align: center;
      padding: 40px 30px;
      background: linear-gradient(135deg, #f8f9ff 0%, #e8f0ff 100%);
      border-radius: 12px;
      margin-bottom: 20px;
    }

    .processing-content {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
    }

    .processing-spinner {
      font-size: 32px;
      color: #667eea;
    }

    .processing-message h3 {
      color: #333;
      font-size: 20px;
      font-weight: 600;
      margin: 0;
    }

    .processing-message p {
      color: #666;
      font-size: 14px;
      margin: 0;
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

    .payment-methods-info {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
    }

    .payment-methods-info h3 {
      font-size: 16px;
      font-weight: 600;
      color: #1f2937;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .payment-methods-info p {
      font-size: 14px;
      color: #6b7280;
      line-height: 1.5;
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

      <div class="payment-section">
        <div class="section-title">
          <i class="fas fa-credit-card"></i>
          Payment Methods
        </div>
        
        <div class="payment-methods-info">
          <h3><i class="fas fa-shield-alt"></i> All Payment Methods Available</h3>
          <p>You'll be redirected to Tap's secure payment page where you can choose from all available payment methods including cards, digital wallets, and local payment options.</p>
        </div>
      </div>

      <!-- Create Charge Button -->
      <button id="create-charge-btn" type="button" class="payment-button">
        <div class="loading-spinner" id="loading-spinner"></div>
        <span id="button-text">Proceed to Payment</span>
      </button>

      <!-- Processing Message (shown when auto-processing) -->
      <div id="processing-message" class="processing-message" style="display: none;">
        <div class="processing-content">
          <div class="processing-spinner">
            <i class="fas fa-spinner fa-spin"></i>
          </div>
          <h3>Processing Payment...</h3>
          <p>Please wait while we redirect you to the secure payment page.</p>
        </div>
      </div>

      <!-- Result display -->
      <div class="result-section" id="result-section" style="display: none;">
        <div class="section-title">
          <i class="fas fa-receipt"></i>
          Transaction Details
        </div>
        <div id="charge-result" class="result-content"></div>
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

  <script>
    // Suppress all extension-related console errors
    (function() {
      const originalConsoleError = console.error;
      const originalConsoleWarn = console.warn;
      
      console.error = function(...args) {
        const message = args.join(' ');
        if (message.includes('Unable to parse event message') ||
            message.includes('CGK9cZpr.js') ||
            message.includes('content_script') ||
            message.includes('extension') ||
            message.includes('devtools') ||
            message.includes('angular') ||
            message.includes('__NG_DEVTOOLS_EVENT__')) {
          return; // Completely suppress these errors
        }
        originalConsoleError.apply(console, args);
      };
      
      console.warn = function(...args) {
        const message = args.join(' ');
        if (message.includes('Unable to parse event message') ||
            message.includes('CGK9cZpr.js') ||
            message.includes('content_script') ||
            message.includes('extension') ||
            message.includes('devtools') ||
            message.includes('angular')) {
          return; // Completely suppress these warnings
        }
        originalConsoleWarn.apply(console, args);
      };
    })();

    // Global error handler to suppress extension-related errors
    window.addEventListener('error', function(event) {
      if (event.filename && (
          event.filename.includes('chrome-extension://') ||
          event.filename.includes('moz-extension://') ||
          event.filename.includes('safari-extension://') ||
          event.filename.includes('content_script') ||
          event.filename.includes('CGK9cZpr.js') ||
          event.filename.includes('extension') ||
          event.filename.includes('devtools') ||
          event.filename.includes('angular')
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
          event.reason.message.includes('content_script') ||
          event.reason.message.includes('CGK9cZpr.js') ||
          event.reason.message.includes('devtools') ||
          event.reason.message.includes('angular')
        )) {
        event.preventDefault();
        return false;
      }
    });

    // Additional console error filtering (redundant but extra safety)
    const originalConsoleError = console.error;
    console.error = function(...args) {
      const message = args.join(' ');
      if (message.includes('Unable to parse event message') ||
          message.includes('CGK9cZpr.js') ||
          message.includes('content_script') ||
          message.includes('extension') ||
          message.includes('devtools') ||
          message.includes('angular') ||
          message.includes('__NG_DEVTOOLS_EVENT__')) {
        return; // Suppress extension-related console errors
      }
      originalConsoleError.apply(console, args);
    };

    // GoHighLevel iframe communication
    let paymentData = null;
    let isReady = false;

    // Validate GHL message structure according to documentation
    function isValidGHLMessage(data) {
      if (!data || typeof data !== 'object') {
        return false;
      }
      
      const validTypes = ['payment_initiate_props', 'setup_initiate_props'];
      if (!data.type || !validTypes.includes(data.type)) {
        console.log('❌ Invalid message type:', data.type);
        return false;
      }
      
      if (data.type === 'payment_initiate_props') {
        const hasRequiredFields = data.publishableKey && 
                                 data.amount && 
                                 data.currency && 
                                 data.mode && 
                                 data.orderId && 
                                 data.transactionId && 
                                 data.locationId;
        
        if (!hasRequiredFields) {
          console.log('❌ Missing required fields for payment_initiate_props:', {
            publishableKey: !!data.publishableKey,
            amount: !!data.amount,
            currency: !!data.currency,
            mode: !!data.mode,
            orderId: !!data.orderId,
            transactionId: !!data.transactionId,
            locationId: !!data.locationId
          });
        }
        
        return hasRequiredFields;
      }
      
      if (data.type === 'setup_initiate_props') {
        const hasRequiredFields = data.publishableKey && 
                                 data.currency && 
                                 data.mode === 'setup' && 
                                 data.contact && 
                                 data.contact.id && 
                                 data.locationId;
        
        if (!hasRequiredFields) {
          console.log('❌ Missing required fields for setup_initiate_props:', {
            publishableKey: !!data.publishableKey,
            currency: !!data.currency,
            mode: data.mode,
            contact: !!data.contact,
            contactId: !!data.contact?.id,
            locationId: !!data.locationId
          });
        }
        
        return hasRequiredFields;
      }
      
      return true;
    }

    // Check if message is from extensions
    function isExtensionMessage(data) {
      if (!data || typeof data !== 'object') {
        return false;
      }
      
      // Extension-specific checks
      if (data.__NG_DEVTOOLS_EVENT__ || 
          data.topic === 'handshake' || 
          data.topic === 'detectAngular' ||
          data.__ignore_ng_zone__ !== undefined ||
          data.isIvy !== undefined ||
          data.isAngular !== undefined) {
        return true;
      }
      
      // Source checks
      if (data.source && (
          data.source.includes('extension') ||
          data.source.includes('devtools') ||
          data.source.includes('content-script') ||
          data.source.includes('angular') ||
          data.source.includes('CGK9cZpr.js')
        )) {
        return true;
      }
      
      // Type checks
      if (data.type && (
          data.type.includes('extension') ||
          data.type.includes('devtools') ||
          data.type.includes('angular')
        )) {
        return true;
      }
      
      // Generic ready events without proper type
      if (data.ready === true && !data.type) {
        return true;
      }
      
      // Handshake with args
      if (data.args !== undefined && data.topic === 'handshake') {
        return true;
      }
      
      return false;
    }

    // Check if message looks like it could be from GHL
    function isPotentialGHLMessage(data) {
      if (!data || typeof data !== 'object') {
        return false;
      }
      
      // Check for exact GHL message types
      if (data.type === 'payment_initiate_props' || data.type === 'setup_initiate_props') {
        return true;
      }
      
      // Check for GHL-like structure
      if (data.publishableKey && (data.amount || data.currency)) {
        return true;
      }
      
      // Check for GHL-like type patterns
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
      
      // Check for contact/customer data
      if (data.contact && typeof data.contact === 'object') {
        return true;
      }
      
      return false;
    }

    // Note: Old message handlers removed - using the new consolidated handler below

    // Send ready event to GoHighLevel parent window
    function sendReadyEvent() {
      const readyEvent = {
        type: 'custom_provider_ready',
        loaded: true,
        addCardOnFileSupported: true,
        // Add additional fields that GHL might expect
        version: '1.0',
        capabilities: ['payment', 'setup'],
        supportedModes: ['payment', 'setup']
      };
      
      console.log('📤 Sending ready event to GHL:', readyEvent);
      console.log('🔍 Window context:', {
        isIframe: window !== window.top,
        hasParent: window.parent !== window,
        hasTop: window.top !== window,
        parentSameAsTop: window.parent === window.top
      });
      
      try {
        // Send to parent window
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(readyEvent), '*');
          console.log('📤 Sent ready event to parent window');
        } else {
          console.warn('⚠️ No parent window available');
        }
        
        // Send to top window if different from parent
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(readyEvent), '*');
          console.log('📤 Sent ready event to top window');
        } else if (window.top === window.parent) {
          console.log('📤 Top window is same as parent, skipping duplicate send');
        } else {
          console.warn('⚠️ No top window available');
        }
        
        isReady = true;
        console.log('✅ Payment iframe is ready and listening for GHL messages');
        
        
        
      } catch (error) {
        console.warn('⚠️ Could not send ready event to parent:', error.message);
        isReady = true;
      }
    }

    // Update payment form with data from GoHighLevel
    function updatePaymentForm(data) {
      console.log('🔄 Updating payment form with GHL data:', data);
      
      if (!data || typeof data !== 'object') {
        console.error('❌ Invalid payment data received');
        return;
      }
      
      // Store payment data for later use
      paymentData = data;
      isReady = true;
      
      if (data.amount && data.currency) {
        console.log('💰 Amount received:', data.amount + ' ' + data.currency);
      }
      
      if (data.contact) {
        console.log('👤 Customer info received:', data.contact);
      }
      
      console.log('✅ Payment data received from GoHighLevel successfully!');
      
      // Auto-create charge and redirect immediately
      createChargeAndRedirect();
    }

    // Update payment form for setup (Add Card on File) flow
    function updatePaymentFormForSetup(data) {
      console.log('🔄 Updating payment form for setup with GHL data:', data);
      
      if (!data || typeof data !== 'object') {
        console.error('❌ Invalid setup data received');
        return;
      }
      
      // Store payment data for later use
      paymentData = data;
      isReady = true;
      
      console.log('💳 Setup mode: Adding card on file');
      
      if (data.contact) {
        console.log('👤 Customer info for setup:', data.contact);
      }
      
      console.log('✅ Card setup data received from GoHighLevel successfully!');
      
      // Handle card setup (if implementing card on file)
      handleCardSetup();
    }

    // Send success response to GoHighLevel
    function sendSuccessResponse(chargeId) {
      const successEvent = {
        type: 'custom_element_success_response',
        chargeId: chargeId
      };
      
      console.log('✅ Sending success response to GHL:', successEvent);
      
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(successEvent), '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(successEvent), '*');
        }
      } catch (error) {
        console.warn('⚠️ Could not send success response to parent:', error.message);
      }
    }

    // Send setup success response to GoHighLevel
    function sendSetupSuccessResponse() {
      const successEvent = {
        type: 'custom_element_success_response'
      };
      
      console.log('✅ Sending setup success response to GHL:', successEvent);
      
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(successEvent), '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(successEvent), '*');
        }
      } catch (error) {
        console.warn('⚠️ Could not send setup success response to parent:', error.message);
      }
    }

    // Send error response to GoHighLevel
    function sendErrorResponse(errorMessage) {
      const errorEvent = {
        type: 'custom_element_error_response',
        error: {
          description: errorMessage
        }
      };
      
      console.log('❌ Sending error response to GHL:', errorEvent);
      
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(errorEvent), '*');
        }
        
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(errorEvent), '*');
        }
      } catch (error) {
        console.warn('⚠️ Could not send error response to parent:', error.message);
      }
    }

    // UI Helper functions
    function showLoading() {
      document.getElementById('loading-spinner').style.display = 'inline-block';
      document.getElementById('button-text').textContent = 'Creating Charge...';
      document.getElementById('create-charge-btn').disabled = true;
    }

    function hideLoading() {
      document.getElementById('loading-spinner').style.display = 'none';
      document.getElementById('button-text').textContent = 'Proceed to Payment';
      document.getElementById('create-charge-btn').disabled = false;
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
      const resultContent = document.getElementById('charge-result');
      
      resultContent.textContent = 'Charge Response:\n' + JSON.stringify(data, null, 2);
      resultSection.style.display = 'block';
      
      resultSection.scrollIntoView({ behavior: 'smooth' });
    }

    // Create charge using the new Charge API
    async function createCharge() {
      if (!paymentData) {
        console.error('❌ No payment data available');
        console.log('🔍 Current paymentData:', paymentData);
        console.log('🔍 isReady status:', isReady);
        showError('No payment data received from GoHighLevel. Please ensure the integration is properly configured.');
        return;
      }

      console.log('🚀 Starting charge creation with payment data:', paymentData);
      showLoading();
      hideMessages();

      try {
        // Validate required fields
        if (!paymentData.amount || !paymentData.currency) {
          throw new Error('Missing required payment data: amount or currency');
        }

        const chargeData = {
          amount: paymentData.amount,
          currency: paymentData.currency,
          customer: paymentData.contact ? {
            first_name: paymentData.contact.name?.split(' ')[0] || 'Customer',
            last_name: paymentData.contact.name?.split(' ').slice(1).join(' ') || 'User',
            email: paymentData.contact.email || 'customer@example.com',
            phone: {
              country_code: '962',
              number: paymentData.contact.contact?.replace(/\D/g, '') || '790000000'
            }
          } : null,
          description: `Payment for ${paymentData.productDetails?.productId || 'product'}`,
          orderId: paymentData.orderId,
          transactionId: paymentData.transactionId,
          locationId: paymentData.locationId
        };

        console.log('🚀 Creating charge with data:', chargeData);

        // Call Tap Payments API directly
        console.log('🚀 Calling Laravel API to create Tap charge...');
        
        const tapResponse = await fetch('/api/charge/create-tap', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify({
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
            merchant: {
              id: paymentData.locationId || '1234'
            },
            post: {
              url: window.location.origin + '/charge/webhook'
            },
            redirect: {
              url: window.location.origin + '/charge/redirect'
            }
          })
        });

        console.log('📡 Response status:', tapResponse.status);
        console.log('📡 Response headers:', tapResponse.headers);
        
        let result;
        try {
          result = await tapResponse.json();
          console.log('📡 Response data:', result);
        } catch (e) {
          console.error('❌ Failed to parse JSON response:', e);
          const textResponse = await tapResponse.text();
          console.error('❌ Raw response:', textResponse);
          throw new Error('Invalid JSON response from server');
        }

        if (tapResponse.ok && result.success && result.charge) {
          console.log('✅ Tap charge created successfully:', result.charge);
          showSuccess('🎉 Charge created successfully! Redirecting to payment page...');
          showResult(result.charge);
          
          // Open Tap's checkout URL in the same window
          if (result.charge.transaction?.url) {
            console.log('🔗 Redirecting to Tap checkout:', result.charge.transaction.url);
            setTimeout(() => {
              window.location.href = result.charge.transaction.url;
            }, 2000);
          } else {
            showError('No checkout URL received from Tap');
            hideLoading();
          }
        } else {
          console.error('❌ Tap charge creation failed:', result);
          showError(result.message || 'Failed to create charge with Tap');
          hideLoading();
          
          // Send error response to GoHighLevel
          sendErrorResponse(result.message || 'Failed to create charge with Tap');
        }
      } catch (error) {
        console.error('❌ Error creating charge:', error);
        showError('An error occurred while creating the charge. Please try again.');
        hideLoading();
        
        // Send error response to GoHighLevel
        sendErrorResponse(error.message || 'An error occurred while creating the charge');
      }
    }

    // Listen for GHL payment events
    window.addEventListener('message', (event) => {
      console.log('📨 Received message from parent:', event.data);
      
      if (event.data.type === 'payment_initiate_props') {
        console.log('💰 GHL Payment event received:', event.data);
        paymentData = event.data;
        isReady = true;
        
        // Immediately hide all UI and show loading only
        document.body.innerHTML = `
          <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-family: 'Inter', sans-serif;">
            <div style="background: white; padding: 50px; border-radius: 20px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.15); max-width: 400px; width: 90%;">
              <div style="width: 80px; height: 80px; border: 6px solid #f3f3f3; border-top: 6px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 30px;"></div>
              <h2 style="color: #333; margin-bottom: 15px; font-size: 24px; font-weight: 600;">Processing Payment</h2>
              <p style="color: #666; font-size: 16px; line-height: 1.5;">Creating secure payment session...</p>
              <div style="margin-top: 20px; color: #999; font-size: 14px;">Please wait while we redirect you</div>
            </div>
          </div>
          <style>
            @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
          </style>
        `;
        
        // Auto-create charge and redirect immediately
        createChargeAndRedirect();
      } else if (event.data.type === 'setup_initiate_props') {
        console.log('💳 GHL Setup event received:', event.data);
        paymentData = event.data;
        isReady = true;
        
        // Handle card setup (if implementing card on file)
        handleCardSetup();
      }
    });

    // Auto-create charge and redirect function
    async function createChargeAndRedirect() {
      console.log('🚀 Auto-creating charge and redirecting to Tap checkout...');
      console.log('📊 Payment data available:', paymentData);

      try {
        // Validate required fields
        if (!paymentData || !paymentData.amount || !paymentData.currency) {
          console.error('❌ Missing payment data:', { paymentData, amount: paymentData?.amount, currency: paymentData?.currency });
          throw new Error('Missing required payment data: amount or currency');
        }

        console.log('🚀 Creating charge with data:', paymentData);
        console.log('🔗 API endpoint: /api/charge/create-tap');

        // Prepare the request body
        const requestBody = {
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
          merchant: {
            id: paymentData.locationId || '1234'
          },
          post: {
            url: window.location.origin + '/charge/webhook'
          },
          redirect: {
            url: window.location.origin + '/payment/success?charge_id='
          }
        };

        console.log('📤 Sending request to API:', {
          url: '/api/charge/create-tap',
          method: 'POST',
          body: requestBody
        });

        // Call Laravel API to create Tap charge
        const tapResponse = await fetch('/api/charge/create-tap', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify(requestBody)
        });

        console.log('📡 Response status:', tapResponse.status);
        console.log('📡 Response headers:', Object.fromEntries(tapResponse.headers.entries()));
        
        let result;
        try {
          result = await tapResponse.json();
          console.log('📡 Response data:', result);
          console.log('✅ API call successful:', result.success);
        } catch (e) {
          console.error('❌ Failed to parse JSON response:', e);
          const textResponse = await tapResponse.text();
          console.error('❌ Raw response:', textResponse);
          throw new Error('Invalid JSON response from server');
        }

        if (tapResponse.ok && result.success && result.charge) {
          console.log('✅ Tap charge created successfully:', result.charge);
          console.log('🆔 Charge ID:', result.charge.id);
          console.log('🔗 Transaction URL:', result.charge.transaction?.url);
          
          // Store charge ID and transaction ID for later verification
          sessionStorage.setItem('tap_charge_id', result.charge.id);
          sessionStorage.setItem('ghl_transaction_id', paymentData.transactionId);
          sessionStorage.setItem('ghl_order_id', paymentData.orderId);
          
          console.log('💾 Stored in sessionStorage:', {
            tap_charge_id: result.charge.id,
            ghl_transaction_id: paymentData.transactionId,
            ghl_order_id: paymentData.orderId
          });
          
          // Redirect to Tap checkout immediately
          if (result.charge.transaction?.url) {
            console.log('🔗 Redirecting to Tap checkout:', result.charge.transaction.url);
            console.log('🚀 About to redirect...');
            window.location.href = result.charge.transaction.url;
          } else {
            console.error('❌ No checkout URL received from Tap');
            throw new Error('No checkout URL received from Tap');
          }
        } else {
          console.error('❌ Tap charge creation failed:', result);
          
          // Show error message
          document.body.innerHTML = `
            <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-family: 'Inter', sans-serif;">
              <div style="background: white; padding: 50px; border-radius: 20px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.15); max-width: 400px; width: 90%;">
                <div style="color: #ef4444; font-size: 64px; margin-bottom: 20px;">⚠️</div>
                <h2 style="color: #333; margin-bottom: 15px; font-size: 24px; font-weight: 600;">Payment Failed</h2>
                <p style="color: #666; font-size: 16px; line-height: 1.5; margin-bottom: 30px;">${result.message || 'Failed to create charge with Tap'}</p>
                <button onclick="window.location.reload()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; transition: all 0.3s ease;">Try Again</button>
              </div>
            </div>
          `;
          
          sendErrorResponse(result.message || 'Failed to create charge with Tap');
        }
      } catch (error) {
        console.error('❌ Error creating charge:', error);
        
        // Show error message
        document.body.innerHTML = `
          <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-family: 'Inter', sans-serif;">
            <div style="background: white; padding: 50px; border-radius: 20px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.15); max-width: 400px; width: 90%;">
              <div style="color: #ef4444; font-size: 64px; margin-bottom: 20px;">⚠️</div>
              <h2 style="color: #333; margin-bottom: 15px; font-size: 24px; font-weight: 600;">Payment Error</h2>
              <p style="color: #666; font-size: 16px; line-height: 1.5; margin-bottom: 30px;">${error.message || 'An error occurred while creating the charge'}</p>
              <button onclick="window.location.reload()" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; transition: all 0.3s ease;">Try Again</button>
            </div>
          </div>
        `;
        
        sendErrorResponse(error.message || 'An error occurred while creating the charge');
      }
    }

    // Handle card setup (for future card on file implementation)
    function handleCardSetup() {
      console.log('💳 Card setup not yet implemented');
      sendErrorResponse('Card setup not yet implemented');
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🚀 Charge API Integration Loaded Successfully');
      console.log('🔍 Window context:', {
        isIframe: window !== window.top,
        parentExists: window.parent !== window,
        topExists: window.top !== window
      });
      
      // Show loading state by default
      document.body.innerHTML = `
        <div style="display: flex; align-items: center; justify-content: center; min-height: 100vh; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); font-family: 'Inter', sans-serif;">
          <div style="background: white; padding: 50px; border-radius: 20px; text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.15); max-width: 400px; width: 90%;">
            <div style="width: 80px; height: 80px; border: 6px solid #f3f3f3; border-top: 6px solid #667eea; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 30px;"></div>
            <h2 style="color: #333; margin-bottom: 15px; font-size: 24px; font-weight: 600;">Loading Payment</h2>
            <p style="color: #666; font-size: 16px; line-height: 1.5;">Preparing secure payment environment...</p>
            <div style="margin-top: 20px; color: #999; font-size: 14px;">Please wait</div>
          </div>
        </div>
        <style>
          @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        </style>
      `;
      
      // Send ready event after a short delay
      setTimeout(() => {
        sendReadyEvent();
      }, 1000);

      // For testing purposes - simulate GHL payment data
      if (window.parent === window) {
        console.log('🌐 Running in standalone mode - Testing mode');
        setTimeout(() => {
          console.log('🧪 Simulating GHL payment data and auto-triggering payment...');
          simulateGHLPaymentData();
        }, 2000);
      }
    });

    // Test functions for debugging
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

    function testChargeFlow() {
      console.log('🧪 Testing charge flow...');
      simulateGHLPaymentData();
      
      setTimeout(() => {
        console.log('🧪 Simulating charge creation...');
        createCharge();
      }, 2000);
    }

    // Comprehensive troubleshooting function
    function diagnoseGHLIntegration() {
      console.log('🔍 GHL Integration Diagnosis:');
      console.log('================================');
      
      // Check iframe context
      console.log('📋 Iframe Context:');
      console.log('  - Current URL:', window.location.href);
      console.log('  - Is in iframe:', window !== window.top);
      console.log('  - Has parent:', window.parent !== window);
      console.log('  - Has top:', window.top !== window);
      console.log('  - Parent same as top:', window.parent === window.top);
      
      // Check URLs (if accessible)
      try {
        console.log('  - Parent URL:', window.parent.location?.href || 'Cannot access');
        console.log('  - Top URL:', window.top.location?.href || 'Cannot access');
      } catch (e) {
        console.log('  - Cannot access parent/top URLs (cross-origin)');
      }
      
      // Check referrer and user agent
      console.log('📋 Request Info:');
      console.log('  - Referrer:', document.referrer);
      console.log('  - User Agent:', navigator.userAgent);
      
      // Check if GHL is listening
      console.log('📋 GHL Communication Status:');
      console.log('  - Ready events sent:', isReady);
      console.log('  - Payment data received:', !!paymentData);
      
      // Check for common issues
      console.log('📋 Common Issues Check:');
      console.log('  - Is this loaded from GHL?', document.referrer.includes('gohighlevel') || document.referrer.includes('highlevel'));
      console.log('  - Is HTTPS?', window.location.protocol === 'https:');
      console.log('  - Is localhost?', window.location.hostname === 'localhost');
      
      // Recommendations
      console.log('📋 Recommendations:');
      if (window.location.hostname === 'localhost') {
        console.log('  ⚠️  You are on localhost - GHL may not be able to reach this URL');
        console.log('  💡 Try using ngrok or a public URL for testing');
      }
      if (!document.referrer.includes('gohighlevel') && !document.referrer.includes('highlevel')) {
        console.log('  ⚠️  This page may not be loaded from GoHighLevel');
        console.log('  💡 Ensure this page is embedded as an iframe in GHL');
      }
      if (!paymentData) {
        console.log('  ⚠️  No payment data received from GHL');
        console.log('  💡 Check GHL integration settings and custom provider configuration');
      }
      
      console.log('================================');
    }

    // Make test functions available globally
    window.testChargeIntegration = {
      simulatePayment: simulateGHLPaymentData,
      testFlow: testChargeFlow,
      diagnose: diagnoseGHLIntegration
    };

    console.log('📋 Available test functions:');
    console.log('  - window.testChargeIntegration.testFlow() - Test complete charge flow');
    console.log('  - window.testChargeIntegration.simulatePayment() - Test with mock GHL data');
    console.log('  - window.testChargeIntegration.diagnose() - Diagnose GHL integration issues');
  </script>
</body>
</html>
