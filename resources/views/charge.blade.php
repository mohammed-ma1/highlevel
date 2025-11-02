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
    /* * {
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
    } */

    .payment-container {
      background: transparent;
      max-width: 100%;
      width: 100%;
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
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
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
      position: relative;
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
    }

    .payment-logo-item {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .payment-logo-separator {
      width: 1px;
      height: 20px;
      background: #d1d5db;
    }

    .tap-logo {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: #1A2B70;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 700;
    }

    .tap-text {
      font-size: 14px;
      color: #4b5563;
      font-weight: 500;
    }

    .visa-logo {
      font-size: 14px;
      font-weight: 700;
      color: #1a1f71;
      letter-spacing: 0.5px;
    }

    .mastercard-logo {
      width: 32px;
      height: 20px;
      background: linear-gradient(135deg, #eb001b 0%, #f79e1b 100%);
      border-radius: 4px;
      position: relative;
    }

    .mastercard-logo::before {
      content: '';
      position: absolute;
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: #eb001b;
      top: 2px;
      left: 6px;
    }

    .mastercard-logo::after {
      content: '';
      position: absolute;
      width: 16px;
      height: 16px;
      border-radius: 50%;
      background: #f79e1b;
      top: 2px;
      right: 6px;
    }

    .amex-logo {
      font-size: 12px;
      font-weight: 700;
      color: #006fcf;
      letter-spacing: 0.5px;
    }

    .knet-logo {
      width: 24px;
      height: 24px;
      border-radius: 4px;
      background: #0066cc;
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      font-weight: 700;
    }

    .payment-logo-text {
      font-size: 12px;
      color: #6b7280;
      margin-left: 4px;
    }


    @media (max-width: 768px) {
      .popup-payment-content {
        padding: 40px 30px;
        border-radius: 20px;
        max-width: 100%;
      }

      .proceed-payment-button {
        padding: 14px 28px;
        font-size: 16px;
        min-width: 240px;
      }

      .payment-methods-logos {
        padding: 10px 16px;
        gap: 10px;
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

      .popup-payment-content {
        padding: 30px 20px;
        border-radius: 16px;
      }

      .proceed-payment-button {
        padding: 14px 24px;
        font-size: 15px;
        min-width: 220px;
      }

      .payment-methods-logos {
        padding: 8px 12px;
        gap: 8px;
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
      
      // Hide payment container initially - only show popup for Safari
      const paymentContainer = document.querySelector('.payment-container');
      if (paymentContainer) {
        paymentContainer.style.display = 'none';
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

    function showButton() {
      // Show payment container for error cases (only Safari)
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
          
          // Add retry button event listener
          const retryBtn = document.getElementById('retry-btn');
          if (retryBtn) {
            retryBtn.addEventListener('click', () => {
              createCharge();
            });
          }
        }
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
          
          // Handle payment redirect based on browser
          if (result.charge.transaction?.url) {
            console.log('🔗 Redirecting to Tap checkout:', result.charge.transaction.url);
            
            // Double-check Safari detection before showing popup
            const currentSafariCheck = detectSafari();
            if (currentSafariCheck) {
              // Use popup ONLY for Safari (desktop and mobile) to avoid iframe payment restrictions
              console.log('🍎 Safari detected (desktop/mobile) - showing proceed payment popup');
              console.log('📱 User Agent:', navigator.userAgent);
              const paymentContainer = document.querySelector('.payment-container');
              if (paymentContainer) {
                paymentContainer.style.display = 'block';
              }
              showProceedPaymentPopup(result.charge.transaction.url);
            } else {
              // Direct redirect for all other browsers - NO POPUP
              console.log('🌐 Non-Safari browser detected - using direct redirect (NO POPUP)');
              console.log('🌐 User Agent:', navigator.userAgent);
              setTimeout(() => {
                window.location.href = result.charge.transaction.url;
              }, 500);
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

    // Detect Safari browser (desktop and mobile)
    function detectSafari() {
      const userAgent = navigator.userAgent;
      
      // Check for Chrome first (Chrome on iOS reports as Safari but has Chrome in userAgent)
      if (/Chrome/.test(userAgent) && !/Chromium/.test(userAgent)) {
        // Chrome browser (desktop or mobile)
        return false;
      }
      
      // Check for Firefox
      if (/Firefox/.test(userAgent)) {
        return false;
      }
      
      // Check for Edge
      if (/Edg/.test(userAgent) || /Edge/.test(userAgent)) {
        return false;
      }
      
      // Check for Opera
      if (/Opera/.test(userAgent) || /OPR/.test(userAgent)) {
        return false;
      }
      
      // iOS devices (iPhone, iPad, iPod) - all use Safari
      const isIOS = /iPad|iPhone|iPod/.test(userAgent);
      if (isIOS) {
        // Additional check: iPad on iOS 13+ might report as Mac, so check for touch
        if (/Macintosh/.test(userAgent) && 'ontouchend' in document) {
          return true; // iPad running iOS 13+
        }
        return true; // iPhone/iPod/iPad (iOS < 13)
      }
      
      // macOS Safari (desktop) - must have Safari but not Chrome
      const isMac = /Macintosh/.test(userAgent);
      const hasSafari = /Safari/.test(userAgent);
      const noChrome = !/Chrome/.test(userAgent) && !/Chromium/.test(userAgent);
      
      if (isMac && hasSafari && noChrome) {
        return true; // macOS Safari
      }
      
      // Additional Safari detection for older versions or edge cases
      if (/^((?!chrome|android).)*safari/i.test(userAgent)) {
        // Make sure it's not Chrome by checking vendor
        const isChrome = /Google Inc/.test(navigator.vendor);
        if (!isChrome) {
          return true;
        }
      }
      
      return false;
    }

    // Show proceed payment popup for Safari only
    function showProceedPaymentPopup(url) {
      // Final safety check - ensure this is Safari
      const finalSafariCheck = detectSafari();
      if (!finalSafariCheck) {
        console.error('❌ Security: showProceedPaymentPopup called but not Safari! Redirecting instead.');
        setTimeout(() => {
          window.location.href = url;
        }, 500);
        return;
      }
      
      console.log('🔗 Showing proceed payment popup for Safari:', url);
      console.log('🔍 Final Safari verification:', {
        userAgent: navigator.userAgent,
        platform: navigator.platform,
        isMobile: /iPhone|iPad|iPod/.test(navigator.userAgent) || ('ontouchend' in document)
      });
      
      const paymentBody = document.querySelector('.payment-body');
      const paymentContainer = document.querySelector('.payment-container');
      
      if (paymentBody && paymentContainer) {
        // Show popup content directly without any card before
        paymentBody.innerHTML = `
          <div class="popup-payment-content">
            <!-- Loading Spinner -->
            <div class="loading-spinner-container">
              <div class="payment-loading-spinner"></div>
            </div>
            
            <!-- Proceed Payment Button -->
            <button id="proceed-payment-btn" class="proceed-payment-button">
              <span class="button-arrow">→</span>
              <span class="button-text">الذهاب لصفحة الدفع</span>
            </button>
            
            <!-- Payment Methods -->
            <div class="payment-methods-logos">
            <img src="{{ asset('https://images.leadconnectorhq.com/image/f_webp/q_80/r_1200/u_https://assets.cdn.filesafe.space/xAN9Y8iZDOugbNvKBKad/media/6901e4a9a412c65d60fb7f4b.png') }}" alt="Tap" class="payment-logo">
          </div>
        `;
      }
      
      // Store URL for button click
      window.openPaymentUrl = url;
      
      // Update button to open in parent window
      const proceedBtn = document.getElementById('proceed-payment-btn');
      if (proceedBtn) {
        proceedBtn.onclick = function() {
          // Open in parent window (if in iframe) or top window
          if (window.top && window.top !== window) {
            window.top.location.href = url;
          } else if (window.parent && window.parent !== window) {
            window.parent.location.href = url;
          } else {
            window.location.href = url;
          }
        };
      }
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🚀 Charge API Integration Loaded Successfully');
      
      // Detect Safari (desktop and mobile)
      isSafari = detectSafari();
      console.log('🔍 Browser detection:', {
        isSafari: isSafari,
        userAgent: navigator.userAgent,
        platform: navigator.platform,
        vendor: navigator.vendor,
        hasTouch: 'ontouchend' in document
      });
      
      if (isSafari) {
        console.log('✅ Safari detected - Popup will be shown for payment');
      } else {
        console.log('✅ Non-Safari browser detected - Direct redirect will be used');
      }
      
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
