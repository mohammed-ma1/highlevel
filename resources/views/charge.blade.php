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
  <!-- <style>
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

    .processing-container {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 200px;
      margin: 40px 0;
    }

    .processing-spinner {
      width: 60px;
      height: 60px;
      border: 4px solid #f3f3f3;
      border-top: 4px solid #667eea;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }

    .retry-container {
      display: flex;
      justify-content: center;
      margin-top: 20px;
    }

    .popup-payment-container {
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 500px;
      padding: 60px 40px;
      background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
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
    }

    .popup-payment-icon {
      font-size: 64px;
      color: #667eea;
      margin-bottom: 32px;
      display: block;
    }

    .popup-payment-title {
      font-size: 32px;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 16px;
      letter-spacing: -0.5px;
    }

    .popup-payment-description {
      font-size: 18px;
      color: #6b7280;
      margin-bottom: 48px;
      line-height: 1.6;
      font-weight: 400;
    }

    .proceed-payment-button {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      padding: 24px 48px;
      border-radius: 16px;
      font-size: 20px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
      min-width: 200px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      position: relative;
      overflow: hidden;
    }

    .proceed-payment-button::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
      transition: left 0.5s;
    }

    .proceed-payment-button:hover::before {
      left: 100%;
    }

    .proceed-payment-button:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 35px rgba(102, 126, 234, 0.4);
    }

    .proceed-payment-button:active {
      transform: translateY(-1px);
    }

    .proceed-payment-button:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }

    .proceed-payment-button i {
      font-size: 22px;
    }


    @media (max-width: 768px) {
      .popup-payment-container {
        min-height: 100vh;
        padding: 20px;
        background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
      }

      .popup-payment-content {
        padding: 40px 30px;
        border-radius: 20px;
        max-width: 100%;
      }

      .popup-payment-icon {
        font-size: 56px;
        margin-bottom: 24px;
      }

      .popup-payment-title {
        font-size: 28px;
        margin-bottom: 12px;
      }

      .popup-payment-description {
        font-size: 16px;
        margin-bottom: 40px;
      }

      .proceed-payment-button {
        padding: 20px 40px;
        font-size: 18px;
        min-width: 180px;
      }
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

      .popup-payment-container {
        padding: 15px;
      }

      .popup-payment-content {
        padding: 30px 20px;
        border-radius: 16px;
      }

      .popup-payment-icon {
        font-size: 48px;
        margin-bottom: 20px;
      }

      .popup-payment-title {
        font-size: 24px;
        margin-bottom: 10px;
      }

      .popup-payment-description {
        font-size: 15px;
        margin-bottom: 32px;
      }

      .proceed-payment-button {
        padding: 18px 36px;
        font-size: 16px;
        min-width: 160px;
        border-radius: 12px;
      }
    }
  </style> -->
</head>
<body>
  <div class="payment-container" id="payment-container">
    <div class="payment-body" id="payment-body">
      <!-- Payment form will be populated when GHL data is received -->
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
    let isSafari = false;
    let paymentPopup = null;

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

    // Listen for messages from GoHighLevel parent window
    window.addEventListener('message', function(event) {
      try {
        // Log ALL messages for debugging (we'll filter in the processing)
        console.log('🔍 Received message:', {
          origin: event.origin,
          source: event.source,
          data: event.data,
          dataType: typeof event.data
        });
        
        // Skip if no data
        if (!event.data) {
          console.debug('🔍 Skipping message with no data');
          return;
        }
        
        // First check if it's an extension message and ignore it
        if (isExtensionMessage(event.data)) {
          console.debug('🔍 Ignoring extension message:', event.data);
          return;
        }
        
        // Additional check for extension patterns
        if (event.data && typeof event.data === 'object') {
          const dataStr = JSON.stringify(event.data);
          if (dataStr.includes('CGK9cZpr.js') ||
              dataStr.includes('content_script') ||
              dataStr.includes('extension') ||
              dataStr.includes('devtools') ||
              dataStr.includes('angular') ||
              dataStr.includes('__NG_DEVTOOLS_EVENT__')) {
            console.debug('🔍 Ignoring extension-related message');
            return;
          }
        }
        
        // Parse JSON string if needed
        let parsedData = event.data;
        if (typeof event.data === 'string') {
          try {
            parsedData = JSON.parse(event.data);
            console.log('📦 Parsed JSON data:', parsedData);
          } catch (e) {
            console.log('❌ Failed to parse JSON string:', e.message);
            return;
          }
        }
        
        // Check if it looks like a potential GHL message
        if (!isPotentialGHLMessage(parsedData)) {
          console.debug('🔍 Received non-GHL message (ignored):', {
            origin: event.origin,
            type: parsedData?.type,
            reason: 'Not a GHL message format'
          });
          return;
        }
        
        console.log('🎯 Potential GHL message detected:', parsedData);
        
        // Validate the GHL message structure
        if (!isValidGHLMessage(parsedData)) {
          console.log('❌ Invalid GHL message structure:', parsedData);
          return;
        }
        
        console.log('✅ Valid GHL message received:', parsedData);
        
        // Process valid GHL messages
        if (parsedData.type === 'payment_initiate_props') {
          paymentData = parsedData;
          console.log('✅ GHL Payment data received:', paymentData);
          updatePaymentForm(paymentData);
        } else if (parsedData.type === 'setup_initiate_props') {
          paymentData = parsedData;
          console.log('✅ GHL Setup data received:', paymentData);
          updatePaymentFormForSetup(paymentData);
        }
      } catch (error) {
        console.error('❌ Error processing message from parent:', error);
      }
    });

    // Fallback message handler for non-standard GHL messages
    window.addEventListener('message', function(event) {
      try {
        // Parse JSON string if needed
        let parsedData = event.data;
        if (typeof event.data === 'string') {
          try {
            parsedData = JSON.parse(event.data);
          } catch (e) {
            return; // Not JSON, skip
          }
        }
        
        // Skip if already processed by main handler
        if (parsedData && typeof parsedData === 'object' && 
            (parsedData.type === 'payment_initiate_props' || parsedData.type === 'setup_initiate_props')) {
          return;
        }
        
        // Check for GHL-like data that might not match exact format
        if (parsedData && typeof parsedData === 'object' && 
            (parsedData.publishableKey || parsedData.amount || parsedData.orderId)) {
          
          console.log('🔄 Fallback: Processing potential GHL message:', parsedData);
          
          // Try to construct a valid payment message
          if (parsedData.publishableKey && parsedData.amount && parsedData.currency) {
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
            
            console.log('🔄 Fallback: Constructed payment data:', fallbackData);
            paymentData = fallbackData;
            updatePaymentForm(fallbackData);
          }
        }
      } catch (error) {
        console.error('❌ Error processing message from parent:', error);
      }
    });

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
      
      if (data.contact) {
        console.log('👤 Customer info received:', data.contact);
      }
      
      // Automatically create charge
      setTimeout(() => {
        console.log('🚀 Auto-creating charge...');
        createCharge();
      }, 0);
    }

    // Update payment form for setup (Add Card on File) flow
    function updatePaymentFormForSetup(data) {
      console.log('🔄 Updating payment form for setup with GHL data:', data);
      
      if (!data || typeof data !== 'object') {
        console.error('❌ Invalid setup data received');
        return;
      }
      
      const amountDisplay = document.getElementById('amount-display');
      if (amountDisplay) {
        amountDisplay.textContent = 'Card Setup';
        console.log('💳 Setup mode: Adding card on file');
      }
      
      if (data.contact) {
        console.log('👤 Customer info for setup:', data.contact);
      }
      
      showSuccess('✅ Card setup data received from GoHighLevel successfully!');
      setTimeout(() => {
        hideMessages();
      }, 3000);
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
      const loadingSpinner = document.getElementById('loading-spinner');
      const buttonText = document.getElementById('button-text');
      const createChargeBtn = document.getElementById('create-charge-btn');
      
      if (loadingSpinner) loadingSpinner.style.display = 'inline-block';
      if (buttonText) buttonText.textContent = 'Creating Charge...';
      if (createChargeBtn) createChargeBtn.disabled = true;
    }

    function hideLoading() {
      const loadingSpinner = document.getElementById('loading-spinner');
      const buttonText = document.getElementById('button-text');
      const createChargeBtn = document.getElementById('create-charge-btn');
      
      if (loadingSpinner) loadingSpinner.style.display = 'none';
      if (buttonText) buttonText.textContent = 'Proceed to Payment';
      if (createChargeBtn) createChargeBtn.disabled = false;
    }

    function showProcessingState() {
      // Hide all payment UI elements
      document.getElementById('create-charge-btn').style.display = 'none';
      document.getElementById('payment-form').style.display = 'none';
      document.getElementById('waiting-state').style.display = 'none';
      
      // Hide the entire payment body and show only spinner
      const paymentBody = document.querySelector('.payment-body');
      paymentBody.innerHTML = `
        <div class="payment-amount">
          <div class="amount-label">Amount to Pay</div>
          <div class="amount-value" id="amount-display">${paymentData ? paymentData.amount + ' ' + paymentData.currency : '1.00 JOD'}</div>
        </div>
        
        <div class="processing-container">
          <div class="processing-spinner"></div>
        </div>
      `;
    }

    function hideProcessingState() {
      const processingDiv = document.getElementById('processing-message');
      if (processingDiv) {
        processingDiv.remove();
      }
    }

    function showButton() {
      // Show payment container
      document.getElementById('payment-container').style.display = 'block';
      
      // Keep the clean UI but add a retry button
      const paymentBody = document.querySelector('.payment-body');
      paymentBody.innerHTML = `
        <div class="payment-amount">
          <div class="amount-label">Amount to Pay</div>
          <div class="amount-value" id="amount-display">${paymentData ? paymentData.amount + ' ' + paymentData.currency : '1.00 JOD'}</div>
        </div>

        <div class="error-message" id="error-message"></div>
        <div class="success-message" id="success-message"></div>

        <div class="processing-container">
          <div class="processing-spinner"></div>
        </div>

        <div class="retry-container">
          <button id="retry-btn" type="button" class="payment-button">
            <span>Retry Payment</span>
          </button>
        </div>
      `;

      // Add retry button event listener
      document.getElementById('retry-btn').addEventListener('click', () => {
        createCharge();
      });
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

    function showResult(data) {
      const resultSection = document.getElementById('result-section');
      const resultContent = document.getElementById('charge-result');
      
      if (resultContent) {
        resultContent.textContent = 'Charge Response:\n' + JSON.stringify(data, null, 2);
      }
      
      if (resultSection) {
        resultSection.style.display = 'block';
        resultSection.scrollIntoView({ behavior: 'smooth' });
      }
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
      
      // Keep showing only the spinner - no UI changes needed

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
          
          // Handle payment redirect based on browser
          if (result.charge.transaction?.url) {
            console.log('🔗 Redirecting to Tap checkout:', result.charge.transaction.url);
            
            if (isSafari) {
              // Use popup for Safari to avoid iframe payment restrictions
              console.log('🍎 Safari detected - using popup for payment');
              const popupOpened = openPaymentPopup(result.charge.transaction.url);
              
              if (!popupOpened) {
                // Show manual payment option instead of direct redirect
                console.log('⚠️ Popup blocked - showing manual payment option');
                // The showManualPaymentButton function is already called in openPaymentPopup
                return; // Don't proceed with direct redirect
              }
            } else {
              // Direct redirect for other browsers
              console.log('🌐 Non-Safari browser - using direct redirect');
              setTimeout(() => {
                window.location.href = result.charge.transaction.url;
              }, 2000);
            }
          } else {
            showError('No checkout URL received from Tap');
            showButton();
          }
        } else {
          console.error('❌ Tap charge creation failed:', result);
          showError(result.message || 'Failed to create charge with Tap');
          showButton();
          
          // Send error response to GoHighLevel
          sendErrorResponse(result.message || 'Failed to create charge with Tap');
        }
      } catch (error) {
        console.error('❌ Error creating charge:', error);
        showError('An error occurred while creating the charge. Please try again.');
        showButton();
        
        // Send error response to GoHighLevel
        sendErrorResponse(error.message || 'An error occurred while creating the charge');
      }
    }

    // Detect Safari browser
    function detectSafari() {
      const userAgent = navigator.userAgent;
      const isSafari = /^((?!chrome|android).)*safari/i.test(userAgent);
      const isIOS = /iPad|iPhone|iPod/.test(userAgent);
      const isMacSafari = /Macintosh/.test(userAgent) && /Safari/.test(userAgent) && !/Chrome/.test(userAgent);
      
      return isSafari || isIOS || isMacSafari;
    }

    // Open payment in popup for Safari compatibility
    function openPaymentPopup(url) {
      console.log('🔗 Opening payment popup for Safari compatibility:', url);
      
      // Try multiple popup approaches for better compatibility
      const popupFeatures = 'width=800,height=600,scrollbars=yes,resizable=yes,status=yes,location=yes,toolbar=no,menubar=no,left=' + (screen.width/2 - 400) + ',top=' + (screen.height/2 - 300);
      
      // First attempt: Standard popup
      paymentPopup = window.open(url, 'tap_payment', popupFeatures);
      
      if (!paymentPopup || paymentPopup.closed || typeof paymentPopup.closed === 'undefined') {
        console.warn('⚠️ First popup attempt failed, trying alternative method...');
        
        // Second attempt: Try with different features
        paymentPopup = window.open(url, 'tap_payment', 'width=800,height=600,scrollbars=yes,resizable=yes');
        
        if (!paymentPopup || paymentPopup.closed || typeof paymentPopup.closed === 'undefined') {
          console.log('⚠️ Popup blocked - showing clean payment interface');
          
          // Show clean popup with proceed button
          showManualPaymentButton(url);
          return false;
        }
      }
      
      // Focus the popup
      try {
        paymentPopup.focus();
      } catch (e) {
        console.warn('⚠️ Could not focus popup:', e.message);
      }
      
      // Monitor popup for completion
      const checkClosed = setInterval(() => {
        if (paymentPopup.closed) {
          clearInterval(checkClosed);
          console.log('🔍 Payment popup closed');
          
          // Check if payment was successful by querying the status
          checkPaymentStatus();
        }
      }, 1000);
      
      // Listen for messages from popup
      const messageHandler = (event) => {
        if (event.data && event.data.type === 'payment_completed') {
          console.log('📨 Received payment completion message from popup:', event.data);
          
          if (event.data.success) {
            console.log('✅ Payment completed successfully via popup');
            showSuccess('🎉 Payment completed successfully!');
            sendSuccessResponse(event.data.params?.charge_id || 'popup_success');
            
            // Auto-close after success
            setTimeout(() => {
              if (window.parent && window.parent !== window) {
                window.parent.postMessage(JSON.stringify({
                  type: 'payment_completed',
                  success: true,
                  chargeId: event.data.params?.charge_id || 'popup_success'
                }), '*');
              }
            }, 2000);
          } else {
            console.log('❌ Payment failed via popup');
            showError('Payment was not completed. Please try again.');
            sendErrorResponse('Payment was not completed');
          }
          
          // Remove the message listener
          window.removeEventListener('message', messageHandler);
        }
      };
      
      window.addEventListener('message', messageHandler);
      
      return true;
    }

    // Show clean popup with proceed button
    function showManualPaymentButton(url) {
      const paymentBody = document.querySelector('.payment-body');
      if (paymentBody) {
        paymentBody.innerHTML = `
          <div class="popup-payment-container">
            <div class="popup-payment-content">
              <div class="popup-payment-icon">
                <i class="fas fa-credit-card"></i>
              </div>
              <h2 class="popup-payment-title">Complete Your Payment</h2>
              <p class="popup-payment-description">Click the button below to proceed with your payment</p>
              <button id="proceed-payment-btn" class="proceed-payment-button" onclick="openPaymentInNewTab('${url}')">
                <i class="fas fa-arrow-right"></i>
                Proceed
              </button>
            </div>
          </div>
        `;
      }
    }

    // Open payment in new tab and start monitoring
    function openPaymentInNewTab(url) {
      console.log('🔗 Opening payment in new tab:', url);
      
      // Open in new tab
      const newTab = window.open(url, '_blank');
      
      if (newTab) {
        console.log('✅ New tab opened successfully');
        
        // Update UI to show monitoring state
        const paymentBody = document.querySelector('.payment-body');
        if (paymentBody) {
          paymentBody.innerHTML = `
            <div class="popup-payment-container">
              <div class="popup-payment-content">
                <div class="popup-payment-icon" style="color: #3b82f6;">
                  <i class="fas fa-external-link-alt"></i>
                </div>
                <h2 class="popup-payment-title">Payment Opened</h2>
                <p class="popup-payment-description">Payment page opened in new tab. Complete your payment and we'll automatically detect when it's done.</p>
                <button id="check-payment-status-btn" class="proceed-payment-button" onclick="checkPaymentStatus()">
                  <i class="fas fa-sync-alt"></i>
                  Check Status
                </button>
              </div>
            </div>
          `;
        }
        
        // Start automatic status checking
        startAutomaticStatusCheck();
      } else {
        console.error('❌ Failed to open new tab');
        showError('Failed to open payment page. Please check your browser settings and try again.');
      }
    }

    // Start automatic status checking
    function startAutomaticStatusCheck() {
      console.log('🔄 Starting automatic payment status monitoring...');
      
      let checkCount = 0;
      const maxChecks = 60; // Check for 5 minutes (60 * 5 seconds)
      
      const statusCheckInterval = setInterval(async () => {
        checkCount++;
        console.log(`🔍 Automatic status check #${checkCount}`);
        
        try {
          const response = await fetch('/api/charge/last-status', {
            method: 'GET',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
          });
          
          if (response.ok) {
            const result = await response.json();
            
            if (result.success && result.charge) {
              const status = result.charge.status;
              
              if (status === 'CAPTURED' || status === 'SUCCESS') {
                console.log('✅ Payment completed successfully!');
                clearInterval(statusCheckInterval);
                
                // Update UI to show success
                const paymentBody = document.querySelector('.payment-body');
                if (paymentBody) {
                  paymentBody.innerHTML = `
                    <div class="popup-payment-container">
                      <div class="popup-payment-content">
                        <div class="popup-payment-icon" style="color: #10b981;">
                          <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="popup-payment-title" style="color: #10b981;">Payment Successful!</h2>
                        <p class="popup-payment-description">Your payment has been processed successfully. Redirecting...</p>
                        <div style="margin-top: 32px;">
                          <div class="proceed-payment-button" style="background: #10b981; cursor: default;">
                            <i class="fas fa-spinner fa-spin"></i>
                            Processing...
                          </div>
                        </div>
                      </div>
                    </div>
                  `;
                }
                
                sendSuccessResponse(result.charge.id);
                
                // Auto-redirect after success
                setTimeout(() => {
                  if (window.parent && window.parent !== window) {
                    window.parent.postMessage(JSON.stringify({
                      type: 'payment_completed',
                      success: true,
                      chargeId: result.charge.id
                    }), '*');
                  }
                }, 2000);
                
                return;
              } else if (status === 'FAILED' || status === 'CANCELLED') {
                console.log('❌ Payment failed');
                clearInterval(statusCheckInterval);
                
                // Update UI to show failure
                const paymentBody = document.querySelector('.payment-body');
                if (paymentBody) {
                  paymentBody.innerHTML = `
                    <div class="popup-payment-container">
                      <div class="popup-payment-content">
                        <div class="popup-payment-icon" style="color: #dc2626;">
                          <i class="fas fa-times-circle"></i>
                        </div>
                        <h2 class="popup-payment-title" style="color: #dc2626;">Payment Failed</h2>
                        <p class="popup-payment-description">Your payment could not be processed. Please try again.</p>
                        <button id="retry-payment-btn" class="proceed-payment-button" onclick="location.reload()" style="background: #dc2626;">
                          <i class="fas fa-redo"></i>
                          Try Again
                        </button>
                      </div>
                    </div>
                  `;
                }
                
                sendErrorResponse('Payment was not completed');
                return;
              }
            }
          }
          
          // Stop checking after max attempts
          if (checkCount >= maxChecks) {
            console.log('⏰ Automatic status check timeout');
            clearInterval(statusCheckInterval);
            
            // Update UI to show timeout
            const paymentBody = document.querySelector('.payment-body');
            if (paymentBody) {
              paymentBody.innerHTML = `
                <div class="popup-payment-container">
                  <div class="popup-payment-content">
                    <div class="popup-payment-icon" style="color: #f59e0b;">
                      <i class="fas fa-clock"></i>
                    </div>
                    <h2 class="popup-payment-title" style="color: #f59e0b;">Still Processing</h2>
                    <p class="popup-payment-description">Payment is taking longer than expected. You can check manually or try again.</p>
                    <button id="check-payment-status-btn" class="proceed-payment-button" onclick="checkPaymentStatus()">
                      <i class="fas fa-sync-alt"></i>
                      Check Status Manually
                    </button>
                    <button id="retry-payment-btn" class="proceed-payment-button" onclick="location.reload()" style="background: #6b7280; margin-top: 10px;">
                      <i class="fas fa-redo"></i>
                      Try Again
                    </button>
                  </div>
                </div>
              `;
            }
          }
        } catch (error) {
          console.error('❌ Error in automatic status check:', error);
        }
      }, 5000); // Check every 5 seconds
    }

    // Check payment status after popup closes
    async function checkPaymentStatus() {
      if (!paymentData) {
        console.error('❌ No payment data available for status check');
        return;
      }
      
      try {
        console.log('🔍 Checking payment status...');
        
        // Show loading state
        const proceedBtn = document.getElementById('proceed-payment-btn');
        if (proceedBtn) {
          proceedBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
          proceedBtn.disabled = true;
        }
        
        // Query the last charge status
        const response = await fetch('/api/charge/last-status', {
          method: 'GET',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          }
        });
        
        if (response.ok) {
          const result = await response.json();
          console.log('📊 Payment status:', result);
          
          if (result.success && result.charge) {
            const status = result.charge.status;
            
            if (status === 'CAPTURED' || status === 'SUCCESS') {
              console.log('✅ Payment successful');
              
              // Update UI to show success
              const paymentBody = document.querySelector('.payment-body');
              if (paymentBody) {
                paymentBody.innerHTML = `
                  <div class="popup-payment-container">
                    <div class="popup-payment-content">
                      <div class="popup-payment-icon" style="color: #10b981;">
                        <i class="fas fa-check-circle"></i>
                      </div>
                      <h2 class="popup-payment-title" style="color: #10b981;">Payment Successful!</h2>
                      <p class="popup-payment-description">Your payment has been processed successfully. Redirecting...</p>
                    </div>
                  </div>
                `;
              }
              
              sendSuccessResponse(result.charge.id);
              
              // Auto-redirect after success
              setTimeout(() => {
                if (window.parent && window.parent !== window) {
                  window.parent.postMessage(JSON.stringify({
                    type: 'payment_completed',
                    success: true,
                    chargeId: result.charge.id
                  }), '*');
                }
              }, 2000);
            } else if (status === 'FAILED' || status === 'CANCELLED') {
              console.log('❌ Payment failed');
              
              // Update UI to show failure
              const paymentBody = document.querySelector('.payment-body');
              if (paymentBody) {
                paymentBody.innerHTML = `
                  <div class="popup-payment-container">
                    <div class="popup-payment-content">
                      <div class="popup-payment-icon" style="color: #dc2626;">
                        <i class="fas fa-times-circle"></i>
                      </div>
                      <h2 class="popup-payment-title" style="color: #dc2626;">Payment Failed</h2>
                      <p class="popup-payment-description">Your payment could not be processed. Please try again.</p>
                      <button id="retry-payment-btn" class="proceed-payment-button" onclick="location.reload()">
                        <i class="fas fa-redo"></i>
                        Try Again
                      </button>
                    </div>
                  </div>
                `;
              }
              
              sendErrorResponse('Payment was not completed');
            } else {
              console.log('⏳ Payment pending:', status);
              
              // Reset button for retry
              if (proceedBtn) {
                proceedBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Proceed';
                proceedBtn.disabled = false;
              }
              
              // Show pending message
              const paymentBody = document.querySelector('.payment-body');
              if (paymentBody) {
                paymentBody.innerHTML = `
                  <div class="popup-payment-container">
                    <div class="popup-payment-content">
                      <div class="popup-payment-icon" style="color: #f59e0b;">
                        <i class="fas fa-clock"></i>
                      </div>
                      <h2 class="popup-payment-title" style="color: #f59e0b;">Payment Processing</h2>
                      <p class="popup-payment-description">Your payment is still being processed. Please wait a moment and check again.</p>
                      <button id="check-again-btn" class="proceed-payment-button" onclick="checkPaymentStatus()">
                        <i class="fas fa-sync-alt"></i>
                        Check Again
                      </button>
                    </div>
                  </div>
                `;
              }
            }
          } else {
            console.log('❌ No charge data found');
            
            // Reset button for retry
            if (proceedBtn) {
              proceedBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Proceed';
              proceedBtn.disabled = false;
            }
            
            // Show error message
            const paymentBody = document.querySelector('.payment-body');
            if (paymentBody) {
              paymentBody.innerHTML = `
                <div class="popup-payment-container">
                  <div class="popup-payment-content">
                    <div class="popup-payment-icon" style="color: #dc2626;">
                      <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2 class="popup-payment-title" style="color: #dc2626;">Unable to Verify</h2>
                    <p class="popup-payment-description">Unable to verify payment status. Please try again.</p>
                    <button id="retry-payment-btn" class="proceed-payment-button" onclick="location.reload()">
                      <i class="fas fa-redo"></i>
                      Try Again
                    </button>
                  </div>
                </div>
              `;
            }
          }
        } else {
          console.error('❌ Failed to check payment status');
          
          // Reset button for retry
          if (proceedBtn) {
            proceedBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Proceed';
            proceedBtn.disabled = false;
          }
          
          // Show error message
          const paymentBody = document.querySelector('.payment-body');
          if (paymentBody) {
            paymentBody.innerHTML = `
              <div class="popup-payment-container">
                <div class="popup-payment-content">
                  <div class="popup-payment-icon" style="color: #dc2626;">
                    <i class="fas fa-exclamation-triangle"></i>
                  </div>
                  <h2 class="popup-payment-title" style="color: #dc2626;">Connection Error</h2>
                  <p class="popup-payment-description">Unable to verify payment status. Please check your connection and try again.</p>
                  <button id="retry-payment-btn" class="proceed-payment-button" onclick="location.reload()">
                    <i class="fas fa-redo"></i>
                    Try Again
                  </button>
                </div>
              </div>
            `;
          }
        }
      } catch (error) {
        console.error('❌ Error checking payment status:', error);
        
        // Reset button for retry
        const proceedBtn = document.getElementById('proceed-payment-btn');
        if (proceedBtn) {
          proceedBtn.innerHTML = '<i class="fas fa-arrow-right"></i> Proceed';
          proceedBtn.disabled = false;
        }
        
        // Show error message
        const paymentBody = document.querySelector('.payment-body');
        if (paymentBody) {
          paymentBody.innerHTML = `
            <div class="popup-payment-container">
              <div class="popup-payment-content">
                <div class="popup-payment-icon" style="color: #dc2626;">
                  <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h2 class="popup-payment-title" style="color: #dc2626;">Error</h2>
                <p class="popup-payment-description">An error occurred while checking payment status. Please try again.</p>
                <button id="retry-payment-btn" class="proceed-payment-button" onclick="location.reload()">
                  <i class="fas fa-redo"></i>
                  Try Again
                </button>
              </div>
            </div>
          `;
        }
      }
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🚀 Charge API Integration Loaded Successfully');
      
      // Detect Safari
      isSafari = detectSafari();
      console.log('🔍 Browser detection:', {
        isSafari: isSafari,
        userAgent: navigator.userAgent
      });
      
      console.log('🔍 Window context:', {
        isIframe: window !== window.top,
        parentExists: window.parent !== window,
        topExists: window.top !== window
      });
      
      // Send ready event immediately
      sendReadyEvent();

      // Wire the button to create charge (if it exists)
      const createChargeBtn = document.getElementById('create-charge-btn');
      if (createChargeBtn) {
        createChargeBtn.addEventListener('click', createCharge);
      }

      // Add keyboard support
      document.addEventListener('keydown', (e) => {
        const createChargeBtn = document.getElementById('create-charge-btn');
        if (e.key === 'Enter' && createChargeBtn && !createChargeBtn.disabled) {
          createChargeBtn.click();
        }
      });
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
