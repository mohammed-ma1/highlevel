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
  <meta http-equiv="Permissions-Policy" content="payment=*">
  <title>{{ config('app.name', 'Laravel') }} — Secure Payment</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <style>


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
      body {
        padding: 15px;
      }

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

      .tap-logo {
        width: 22px;
        height: 22px;
        font-size: 13px;
      }

      .tap-text {
        font-size: 13px;
      }

      .visa-logo {
        font-size: 13px;
      }

      .mastercard-logo {
        width: 28px;
        height: 18px;
      }

      .amex-logo {
        font-size: 11px;
      }

      .knet-logo {
        width: 22px;
        height: 22px;
        font-size: 13px;
      }

      .payment-logo-text {
        font-size: 11px;
      }
    }

    @media (max-width: 480px) {
      body {
        padding: 10px;
      }

      .payment-container {
        margin: 0;
      }
      
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

      .tap-logo {
        width: 20px;
        height: 20px;
        font-size: 12px;
      }

      .tap-text {
        font-size: 12px;
      }

      .visa-logo {
        font-size: 12px;
      }

      .mastercard-logo {
        width: 26px;
        height: 16px;
      }

      .mastercard-logo::before,
      .mastercard-logo::after {
        width: 14px;
        height: 14px;
      }

      .amex-logo {
        font-size: 10px;
      }

      .knet-logo {
        width: 20px;
        height: 20px;
        font-size: 12px;
      }

      .payment-logo-text {
        font-size: 10px;
      }

      .payment-logo-separator {
        height: 18px;
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

  <!-- Payment iframe -->
  <div id="payment-iframe-wrapper" style="display: none; width: 100%; height: 100vh; position: fixed; top: 0; left: 0; z-index: 10000; background: white;">
    <iframe 
      id="payment-iframe" 
      allow="payment *; fullscreen"
      style="width: 100%; height: 100%; border: none;"
      title="Payment Checkout">
    </iframe>
  </div>

  <script>
    // Set up Tap navigation error handler - use multiple methods to catch the error
    console.log('🚀 Setting up Tap navigation error handler...');
    
    (function() {
      if (!window.tapErrorHandlerSetup) {
        window.tapErrorHandlerSetup = true;
        window.tapRedirectHandled = false;
        
        // Method 1: Intercept console.error - this is the PRIMARY method
        const originalConsoleError = console.error.bind(console);
        console.error = function(...args) {
          const errorText = args.join(' ');
          
          // Log all SecurityErrors to debug
          if (errorText.includes('SecurityError') || errorText.includes('tap_process') || errorText.includes('acceptance')) {
            console.log('📢 SecurityError detected in console.error:', errorText.substring(0, 400));
          }
          
          // Check for navigation security error
          if ((errorText.includes('Failed to set a named property') ||
               errorText.includes('Unsafe attempt to initiate navigation') ||
               errorText.includes('permission to navigate') ||
               errorText.includes('SecurityError') ||
               errorText.includes('sandboxed')) &&
              !window.tapRedirectHandled) {
            
            console.log('🔍 Navigation error detected in console.error!', errorText.substring(0, 300));
            
            // Extract URL - try multiple patterns
            let urlMatch = errorText.match(/to\s+['"](https?:\/\/[^'"]+)['"]/i) ||
                          errorText.match(/['"](https?:\/\/[^'"]+tap_process[^'"]+)['"]/i) ||
                          errorText.match(/(https?:\/\/acceptance\.sandbox\.tap\.company[^\s'"]+)/i) ||
                          errorText.match(/(https?:\/\/acceptance\.tap\.company[^\s'"]+)/i);
            
            // If URL is truncated, try to extract the base URL
            if (!urlMatch) {
              const baseMatch = errorText.match(/(https?:\/\/acceptance\.sandbox\.tap\.company\/gosell\/v2\/payment\/tap_process\.aspx)/i);
              if (baseMatch) {
                // We have the base URL, but the chg_url parameter is truncated
                // We'll need to get it from the API or use a different method
                console.warn('⚠️ URL is truncated in error message');
              }
            }
            
            if (urlMatch && urlMatch[1]) {
              let redirectUrl = urlMatch[1].replace(/['"]+$/, '').trim();
              // Remove any trailing incomplete characters
              redirectUrl = redirectUrl.replace(/[^a-zA-Z0-9\/\?\=\&\.\-\:]+$/, '');
              
              if ((redirectUrl.includes('tap_process.aspx') ||
                   redirectUrl.includes('acceptance.sandbox.tap.company') ||
                   redirectUrl.includes('acceptance.tap.company') ||
                   redirectUrl.includes('/gosell/v2/payment/')) &&
                  !window.tapRedirectHandled) {
                
                window.tapRedirectHandled = true;
                console.log('🔗 Extracted URL and redirecting to:', redirectUrl);
                
                // Redirect immediately
                setTimeout(() => {
                  try {
                    if (window.top && window.top !== window) {
                      window.top.location.href = redirectUrl;
                    } else {
                      window.location.href = redirectUrl;
                    }
                  } catch (e) {
                    console.error('Failed to redirect:', e);
                    window.open(redirectUrl, '_top');
                  }
                }, 50);
                return; // Don't log the error
              }
            } else {
              console.warn('⚠️ Could not extract URL from error. Error text:', errorText.substring(0, 500));
            }
          }
          
          originalConsoleError.apply(console, args);
        };
        
        // Method 2: Use window error event listener
        window.addEventListener('error', function(event) {
          if (window.tapRedirectHandled) return;
          
          const errorMsg = (event.message || event.error?.message || '').toString();
          
          if (errorMsg.includes('Failed to set a named property') ||
              errorMsg.includes('Unsafe attempt to initiate navigation') ||
              errorMsg.includes('SecurityError')) {
            
            console.log('🔍 Navigation error detected in window error event!');
            
            const urlMatch = errorMsg.match(/to\s+['"](https?:\/\/[^'"]+)['"]/i) ||
                            errorMsg.match(/(https?:\/\/acceptance\.sandbox\.tap\.company[^\s'"]+)/i);
            
            if (urlMatch && urlMatch[1] && !window.tapRedirectHandled) {
              const redirectUrl = urlMatch[1].replace(/['"]+$/, '').trim();
              
              if (redirectUrl.includes('tap_process.aspx') || redirectUrl.includes('acceptance')) {
                window.tapRedirectHandled = true;
                console.log('🔗 Redirecting to (from error event):', redirectUrl);
                
                setTimeout(() => {
                  if (window.top && window.top !== window) {
                    window.top.location.href = redirectUrl;
                  } else {
                    window.location.href = redirectUrl;
                  }
                }, 100);
              }
            }
          }
        }, true);
        
        // Method 3: Monitor console output using MutationObserver (fallback)
        // This watches for new console entries
        if (typeof MutationObserver !== 'undefined') {
          const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
              mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1 && node.textContent) {
                  const text = node.textContent;
                  if (text.includes('Failed to set a named property') &&
                      text.includes('tap_process') &&
                      !window.tapRedirectHandled) {
                    
                    const urlMatch = text.match(/to\s+['"](https?:\/\/[^'"]+tap_process[^'"]+)['"]/i) ||
                                    text.match(/(https?:\/\/acceptance\.sandbox\.tap\.company[^\s'"]+)/i);
                    
                    if (urlMatch && urlMatch[1]) {
                      const redirectUrl = urlMatch[1].replace(/['"]+$/, '').trim();
                      if (redirectUrl.includes('tap_process.aspx') || redirectUrl.includes('acceptance')) {
                        window.tapRedirectHandled = true;
                        console.log('🔗 Redirecting to (from MutationObserver):', redirectUrl);
                        
                        setTimeout(() => {
                          if (window.top && window.top !== window) {
                            window.top.location.href = redirectUrl;
                          } else {
                            window.location.href = redirectUrl;
                          }
                        }, 100);
                      }
                    }
                  }
                }
              });
            });
          });
          
          // Try to observe console output (may not work in all browsers)
          try {
            const consoleDiv = document.querySelector('body');
            if (consoleDiv) {
              observer.observe(consoleDiv, { childList: true, subtree: true });
            }
          } catch (e) {
            // MutationObserver might not work for console, that's okay
          }
        }
        
        console.log('✅ Tap navigation error handler set up with multiple methods');
      }
    })();
    
    // Suppress all extension-related console errors
    // IMPORTANT: This must come AFTER the Tap error handler so it doesn't interfere
    (function() {
      // Get the current console.error (which should be our Tap handler)
      const currentConsoleError = console.error;
      const originalConsoleWarn = console.warn;
      
      console.error = function(...args) {
        // First, let our Tap handler process it (it's already set up above)
        // Then suppress extension errors
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
        // Call the current console.error (which chains to our Tap handler)
        currentConsoleError.apply(console, args);
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
        
        // Mark GHL as acknowledged when we receive any message from them
        if (!ghlAcknowledged) {
          ghlAcknowledged = true;
          stopReadyEventRetry();
          console.log('✅ GHL acknowledged - received message from GHL');
        }
        
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
            // Mark GHL as acknowledged when we receive any message from them
            if (!ghlAcknowledged) {
              ghlAcknowledged = true;
              stopReadyEventRetry();
              console.log('✅ GHL acknowledged - received fallback message from GHL');
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
            
            console.log('🔄 Fallback: Constructed payment data:', fallbackData);
            paymentData = fallbackData;
            updatePaymentForm(fallbackData);
          }
        }
      } catch (error) {
        console.error('❌ Error processing message from parent:', error);
      }
    });

    // Listen for messages from payment redirect page via localStorage (cross-tab communication)
    // This handles the case where the payment opens in a new tab instead of an iframe
    window.addEventListener('storage', function(event) {
      try {
        // Only process messages from the payment redirect page
        if (event.key && event.key.startsWith('ghl_payment_message_') && event.newValue) {
          console.log('📥 Received message via localStorage (cross-tab):', event.key);
          
          const messageData = JSON.parse(event.newValue);
          
          // Verify it's from the payment redirect page
          if (messageData.source === 'payment_redirect' && messageData.message) {
            const message = messageData.message;
            console.log('✅ Processing payment redirect message:', message);
            
            // Process the message as if it came via postMessage
            // Messages from redirect page have types: custom_element_success_response, custom_element_error_response, custom_element_close_response
            if (message.type === 'custom_element_success_response') {
              console.log('✅ Payment successful:', message.chargeId);
              // Handle success - you may want to call your existing success handler here
              // For now, we'll forward it to GHL via postMessage
              if (window.parent && window.parent !== window) {
                window.parent.postMessage(JSON.stringify(message), '*');
              }
              if (window.top && window.top !== window && window.top !== window.parent) {
                window.top.postMessage(JSON.stringify(message), '*');
              }
            } else if (message.type === 'custom_element_error_response') {
              console.log('❌ Payment error:', message.error);
              // Handle error - forward to GHL
              if (window.parent && window.parent !== window) {
                window.parent.postMessage(JSON.stringify(message), '*');
              }
              if (window.top && window.top !== window && window.top !== window.parent) {
                window.top.postMessage(JSON.stringify(message), '*');
              }
            } else if (message.type === 'custom_element_close_response') {
              console.log('🚪 Payment canceled');
              // Handle close - forward to GHL
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
        console.error('❌ Error processing storage event:', error);
      }
    });

    // Also poll localStorage periodically as a fallback (storage event may not fire in same-tab scenarios)
    let localStoragePollInterval = null;
    const lastProcessedMessages = new Set();
    
    function pollLocalStorageMessages() {
      try {
        // Check all localStorage keys for payment messages
        for (let i = 0; i < localStorage.length; i++) {
          const key = localStorage.key(i);
          if (key && key.startsWith('ghl_payment_message_') && !lastProcessedMessages.has(key)) {
            try {
              const messageDataStr = localStorage.getItem(key);
              if (messageDataStr) {
                const messageData = JSON.parse(messageDataStr);
                
                // Only process recent messages (within last 5 seconds)
                const messageAge = Date.now() - messageData.timestamp;
                if (messageAge < 5000 && messageData.source === 'payment_redirect' && messageData.message) {
                  console.log('📥 Polling: Found unprocessed payment message:', key);
                  lastProcessedMessages.add(key);
                  
                  // Process the message (same logic as storage event handler above)
                  const message = messageData.message;
                  if (message.type === 'custom_element_success_response' || 
                      message.type === 'custom_element_error_response' || 
                      message.type === 'custom_element_close_response') {
                    console.log('✅ Processing polled payment message:', message.type);
                    
                    // Forward to GHL
                    if (window.parent && window.parent !== window) {
                      window.parent.postMessage(JSON.stringify(message), '*');
                    }
                    if (window.top && window.top !== window && window.top !== window.parent) {
                      window.top.postMessage(JSON.stringify(message), '*');
                    }
                  }
                  
                  // Clean up
                  localStorage.removeItem(key);
                } else if (messageAge >= 5000) {
                  // Clean up old messages
                  localStorage.removeItem(key);
                  lastProcessedMessages.delete(key);
                }
              }
            } catch (e) {
              console.warn('⚠️ Error processing localStorage message:', e);
            }
          }
        }
      } catch (error) {
        console.error('❌ Error polling localStorage:', error);
      }
    }
    
    // Start polling every 500ms when page is visible
    if (typeof document !== 'undefined' && document.visibilityState !== undefined) {
      function startPolling() {
        if (document.visibilityState === 'visible') {
          if (!localStoragePollInterval) {
            localStoragePollInterval = setInterval(pollLocalStorageMessages, 500);
            console.log('🔄 Started polling localStorage for payment messages');
          }
        } else {
          if (localStoragePollInterval) {
            clearInterval(localStoragePollInterval);
            localStoragePollInterval = null;
            console.log('⏸️ Stopped polling localStorage (page hidden)');
          }
        }
      }
      
      document.addEventListener('visibilitychange', startPolling);
      startPolling(); // Start immediately
    }

    // Track if GHL has acknowledged (received any message from GHL)
    let ghlAcknowledged = false;
    let readyEventRetryInterval = null;
    let readyEventRetryCount = 0;
    const MAX_RETRY_ATTEMPTS = 20; // Retry for ~10 seconds (20 * 500ms)
    const RETRY_INTERVAL = 500; // Retry every 500ms

    // Send ready event to GoHighLevel parent window with retry mechanism
    function sendReadyEvent(forceRetry = false) {
      // If GHL already acknowledged and we're not forcing a retry, skip
      if (ghlAcknowledged && !forceRetry) {
        return;
      }

      const readyEvent = {
        type: 'custom_provider_ready',
        loaded: true,
        addCardOnFileSupported: true,
        // Add additional fields that GHL might expect
        version: '1.0',
        capabilities: ['payment', 'setup'],
        supportedModes: ['payment', 'setup']
      };
      
      console.log(`📤 Sending ready event to GHL (attempt ${readyEventRetryCount + 1}):`, readyEvent);
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
        
        // Increment retry count
        readyEventRetryCount++;
        
        // If this is the first attempt, start retry interval
        if (readyEventRetryCount === 1 && !ghlAcknowledged) {
          startReadyEventRetry();
        }
        
        // If we've received acknowledgment from GHL, stop retrying
        if (ghlAcknowledged) {
          stopReadyEventRetry();
          console.log('✅ GHL acknowledged - stopped sending ready event');
        } else if (readyEventRetryCount >= MAX_RETRY_ATTEMPTS) {
          stopReadyEventRetry();
          console.warn('⚠️ Max retry attempts reached. GHL may not be listening.');
        } else {
          console.log(`✅ Payment iframe is ready and listening for GHL messages (retry ${readyEventRetryCount}/${MAX_RETRY_ATTEMPTS})`);
        }
        
      } catch (error) {
        console.warn('⚠️ Could not send ready event to parent:', error.message);
        isReady = true;
      }
    }

    // Start retry interval for ready event
    function startReadyEventRetry() {
      if (readyEventRetryInterval) {
        return; // Already started
      }
      
      console.log('🔄 Starting ready event retry mechanism...');
      readyEventRetryInterval = setInterval(() => {
        if (!ghlAcknowledged && readyEventRetryCount < MAX_RETRY_ATTEMPTS) {
          sendReadyEvent(true);
        } else {
          stopReadyEventRetry();
        }
      }, RETRY_INTERVAL);
    }

    // Stop retry interval
    function stopReadyEventRetry() {
      if (readyEventRetryInterval) {
        clearInterval(readyEventRetryInterval);
        readyEventRetryInterval = null;
        console.log('🛑 Stopped ready event retry mechanism');
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
      
      // Hide payment container initially
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
      // Show payment container for error cases
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
      
      // Set up aggressive error monitoring when button is shown
      // This is a fallback in case the global error handler didn't catch the SecurityError
      if (!window.tapErrorMonitorActive) {
        window.tapErrorMonitorActive = true;
        
        // Monitor console for SecurityError messages
        const monitorInterval = setInterval(() => {
          if (window.tapRedirectHandled) {
            clearInterval(monitorInterval);
            return;
          }
          
          // Check if there's a payment iframe that might have errors
          const paymentIframe = document.getElementById('payment-iframe');
          if (paymentIframe && paymentIframe.style.display !== 'none') {
            // Iframe is visible, monitor for errors
            // The global error handler should catch it, but this is a backup
          }
        }, 500);
        
        // Clear monitor after 2 minutes
        setTimeout(() => {
          clearInterval(monitorInterval);
          window.tapErrorMonitorActive = false;
        }, 120000);
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
          
          // Handle payment redirect - use iframe for all browsers
          if (result.charge.transaction?.url) {
            console.log('🔗 Loading Tap checkout in iframe:', result.charge.transaction.url);
              console.log('🌐 User Agent:', navigator.userAgent);
            // Load payment URL in iframe with allow="payment" attribute
            loadPaymentInIframe(result.charge.transaction.url);
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

    // Load payment URL in iframe with allow="payment" attribute (Safari workaround)
    function loadPaymentInIframe(url) {
      console.log('🖼️ Loading payment URL in iframe with allow="payment":', url);
      
      const iframeWrapper = document.getElementById('payment-iframe-wrapper');
      const paymentIframe = document.getElementById('payment-iframe');
      
      if (!iframeWrapper || !paymentIframe) {
        console.error('❌ Payment iframe elements not found, falling back to direct redirect');
        setTimeout(() => {
          window.location.href = url;
        }, 500);
        return;
      }

      // CRITICAL: Remove sandbox attribute if present - it blocks navigation
      // For cross-origin iframes, we don't need sandbox restrictions
      // The sandbox attribute prevents the iframe from navigating the parent window
      if (paymentIframe.hasAttribute('sandbox')) {
        console.warn('⚠️ Iframe has sandbox attribute, removing it to allow navigation');
        paymentIframe.removeAttribute('sandbox');
      }
      
      // Ensure allow attribute is set for payment requests
      if (!paymentIframe.hasAttribute('allow')) {
        paymentIframe.setAttribute('allow', 'payment *; fullscreen');
      }
      
      // Double-check after a brief delay (in case something adds sandbox dynamically)
      setTimeout(() => {
        if (paymentIframe.hasAttribute('sandbox')) {
          console.warn('⚠️ Sandbox attribute was added after load, removing it');
          paymentIframe.removeAttribute('sandbox');
        }
      }, 500);

      // Store the original charge URL and charge data for potential fallback
      window.tapChargeUrl = url;
      window.tapChargeData = paymentData; // Store charge data to fetch payment URL if needed
      
      // Reset redirect flag for new payment attempt
      window.tapRedirectHandled = false;
      
      // Set up a direct listener for the SecurityError on the iframe
      // This is a fallback in case the global handlers don't catch it
      const iframeErrorHandler = function(event) {
        if (window.tapRedirectHandled) return;
        
        const errorMsg = (event.message || event.error?.message || '').toString();
        console.log('🔍 Iframe error event:', errorMsg.substring(0, 200));
        
        if (errorMsg.includes('Failed to set a named property') ||
            errorMsg.includes('SecurityError') ||
            errorMsg.includes('tap_process')) {
          
          const urlMatch = errorMsg.match(/to\s+['"](https?:\/\/[^'"]+)['"]/i) ||
                          errorMsg.match(/(https?:\/\/acceptance\.sandbox\.tap\.company[^\s'"]+)/i);
          
          if (urlMatch && urlMatch[1] && !window.tapRedirectHandled) {
            const redirectUrl = urlMatch[1].replace(/['"]+$/, '').trim();
            if (redirectUrl.includes('tap_process.aspx') || redirectUrl.includes('acceptance')) {
              window.tapRedirectHandled = true;
              console.log('🔗 Redirecting from iframe error handler:', redirectUrl);
              
              setTimeout(() => {
                if (window.top && window.top !== window) {
                  window.top.location.href = redirectUrl;
                } else {
                  window.location.href = redirectUrl;
                }
              }, 100);
            }
          }
        }
      };
      
      // Try to add error listener to iframe (may not work due to CORS)
      try {
        paymentIframe.contentWindow.addEventListener('error', iframeErrorHandler, true);
      } catch (e) {
        // Can't access iframe contentWindow due to CORS, that's expected
        console.debug('Cannot add error listener to iframe (CORS):', e.message);
      }

      // Hide payment container
      const paymentContainer = document.querySelector('.payment-container');
      if (paymentContainer) {
        paymentContainer.style.display = 'none';
      }

      // Show iframe wrapper
      iframeWrapper.style.display = 'block';
      
      // Set iframe source with allow="payment *" attribute (already set in HTML)
      // IMPORTANT: Set up error monitoring BEFORE loading the URL
      // This ensures we catch the SecurityError as soon as it occurs
      
      // Set up a MutationObserver to watch for error messages in the console
      // This is a last-resort fallback if console.error interceptor doesn't work
      const consoleObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
          mutation.addedNodes.forEach(function(node) {
            if (node.nodeType === 1) {
              const text = node.textContent || node.innerText || '';
              
              // Check for SecurityError with tap_process URL
              if (text.includes('Failed to set a named property') &&
                  text.includes('tap_process') &&
                  !window.tapRedirectHandled) {
                
                console.log('🔍 MutationObserver detected SecurityError in DOM');
                
                // Try to extract URL
                const urlMatch = text.match(/to\s+['"](https?:\/\/[^'"]+tap_process[^'"]+)['"]/i) ||
                                text.match(/(https?:\/\/acceptance\.sandbox\.tap\.company[^\s'"]+)/i);
                
                if (urlMatch && urlMatch[1]) {
                  let redirectUrl = urlMatch[1].replace(/['"]+$/, '').trim();
                  redirectUrl = redirectUrl.replace(/[^a-zA-Z0-9\/\?\=\&\.\-\:]+$/, '');
                  
                  if ((redirectUrl.includes('tap_process.aspx') || redirectUrl.includes('acceptance')) &&
                      !window.tapRedirectHandled) {
                    window.tapRedirectHandled = true;
                    console.log('🔗 Redirecting from MutationObserver:', redirectUrl);
                    
                    setTimeout(() => {
                      if (window.top && window.top !== window) {
                        window.top.location.href = redirectUrl;
                      } else {
                        window.location.href = redirectUrl;
                      }
                    }, 50);
                  }
                }
              }
            }
          });
        });
      });
      
      // Try to observe the console output area (may not work in all browsers)
      try {
        // Observe the document body for any error messages
        consoleObserver.observe(document.body, {
          childList: true,
          subtree: true,
          characterData: true
        });
        
        // Clear observer after 30 seconds
        setTimeout(() => {
          consoleObserver.disconnect();
        }, 30000);
      } catch (e) {
        console.debug('Cannot set up MutationObserver:', e.message);
      }
      
      // Set up a direct error catch mechanism using unhandledrejection
      // This catches promise rejections that might contain the SecurityError
      const unhandledRejectionHandler = function(event) {
        if (window.tapRedirectHandled) return;
        
        const reason = event.reason;
        const reasonMsg = (reason?.message || reason?.toString() || '').toString();
        
        if (reasonMsg.includes('Failed to set a named property') ||
            reasonMsg.includes('SecurityError') ||
            reasonMsg.includes('tap_process') ||
            (reason && reason.name === 'SecurityError')) {
          
          console.log('🔍 Unhandled rejection with SecurityError:', reasonMsg.substring(0, 300));
          
          const urlMatch = reasonMsg.match(/to\s+['"](https?:\/\/[^'"]+)['"]/i) ||
                          reasonMsg.match(/(https?:\/\/acceptance\.sandbox\.tap\.company[^\s'"]+)/i);
          
          if (urlMatch && urlMatch[1] && !window.tapRedirectHandled) {
            let redirectUrl = urlMatch[1].replace(/['"]+$/, '').trim();
            redirectUrl = redirectUrl.replace(/[^a-zA-Z0-9\/\?\=\&\.\-\:]+$/, '');
            
            if ((redirectUrl.includes('tap_process.aspx') || redirectUrl.includes('acceptance')) &&
                !window.tapRedirectHandled) {
              window.tapRedirectHandled = true;
              console.log('🔗 Redirecting from unhandled rejection:', redirectUrl);
              
              setTimeout(() => {
                if (window.top && window.top !== window) {
                  window.top.location.href = redirectUrl;
                } else {
                  window.location.href = redirectUrl;
                }
              }, 50);
            }
          }
        }
      };
      
      window.addEventListener('unhandledrejection', unhandledRejectionHandler);
      
      // Store handler for cleanup
      window.tapUnhandledRejectionHandler = unhandledRejectionHandler;
      
      // Now load the iframe
      paymentIframe.src = url;

      // Listen for messages from iframe (for payment completion)
      const messageHandler = function(event) {
        // Only process messages from our redirect page
        if (event.origin !== window.location.origin) {
          return;
        }

        try {
          let messageData = event.data;
          if (typeof messageData === 'string') {
            messageData = JSON.parse(messageData);
          }

          // Check if it's a payment completion message
          if (messageData && messageData.type && 
              (messageData.type === 'custom_element_success_response' || 
               messageData.type === 'custom_element_error_response' || 
               messageData.type === 'custom_element_close_response')) {
            console.log('📥 Received payment message from iframe:', messageData);
            
            // Hide iframe
            iframeWrapper.style.display = 'none';
            
            // Remove message listener
            window.removeEventListener('message', messageHandler);
            
            // Forward message to GHL
            if (messageData.type === 'custom_element_success_response') {
              sendSuccessResponse(messageData.chargeId);
            } else if (messageData.type === 'custom_element_error_response') {
              sendErrorResponse(messageData.error?.description || 'Payment failed');
            } else if (messageData.type === 'custom_element_close_response') {
              sendErrorResponse('Payment canceled');
            }
          }
        } catch (e) {
          console.debug('Message from iframe (not payment related):', e);
        }
      };

      window.addEventListener('message', messageHandler);

      paymentIframe.onerror = function(error) {
        console.error('❌ Payment iframe error:', error);
        iframeWrapper.style.display = 'none';
        showError('Failed to load payment page in iframe. Trying direct redirect...');
        // Fallback to direct redirect if iframe fails
        setTimeout(() => {
          window.location.href = url;
        }, 1000);
      };

      paymentIframe.onload = function() {
        console.log('✅ Payment iframe loaded');
        
        // Set up aggressive error detection after iframe loads
        // This monitors for the SecurityError and redirects immediately
        let errorCheckCount = 0;
        const maxErrorChecks = 600; // Check for 60 seconds (100ms * 600)
        
        const errorDetectionInterval = setInterval(() => {
          errorCheckCount++;
          
          if (window.tapRedirectHandled) {
            clearInterval(errorDetectionInterval);
            return;
          }
          
          // Every 5 seconds, log that we're still monitoring
          if (errorCheckCount % 50 === 0) {
            console.log('🔍 Still monitoring for SecurityError...', errorCheckCount);
          }
          
          // The error handlers should catch it, but this is a backup
          // that will trigger if the error occurs but handlers miss it
          
          if (errorCheckCount >= maxErrorChecks) {
            clearInterval(errorDetectionInterval);
            console.log('⏱️ Error detection timeout - stopping monitor');
          }
        }, 100);
        
        // Also try to monitor iframe navigation by checking URL periodically
        // This helps us catch the URL before the security error occurs
        let lastCheckedUrl = url;
        const urlMonitor = setInterval(() => {
          if (window.tapRedirectHandled) {
            clearInterval(urlMonitor);
            return;
          }
          
          try {
            const currentUrl = paymentIframe.contentWindow?.location?.href;
            if (currentUrl && currentUrl !== lastCheckedUrl && currentUrl !== 'about:blank') {
              console.log('🔍 Iframe URL changed:', currentUrl);
              lastCheckedUrl = currentUrl;
              
              // Check if it's an external payment URL
              const isExternalPaymentUrl = 
                currentUrl.includes('tap_process.aspx') ||
                currentUrl.includes('acceptance.sandbox.tap.company') ||
                currentUrl.includes('acceptance.tap.company') ||
                currentUrl.includes('/gosell/v2/payment/') ||
                currentUrl.includes('knet');
              
              if (isExternalPaymentUrl && !window.tapRedirectHandled) {
                console.log('🔗 Detected external payment URL in iframe - redirecting top-level window');
                clearInterval(urlMonitor);
                clearInterval(errorDetectionInterval);
                window.tapRedirectHandled = true;
                
                // Redirect the top-level window
                try {
                  if (window.top && window.top !== window) {
                    window.top.location.href = currentUrl;
                  } else {
                    window.location.href = currentUrl;
                  }
                } catch (e) {
                  console.error('Failed to redirect:', e);
                }
              }
            }
          } catch (e) {
            // CORS - can't access iframe URL, which is expected
          }
        }, 200); // Check every 200ms for faster detection
        
        // Clear monitors after 5 minutes
        setTimeout(() => {
          clearInterval(urlMonitor);
          clearInterval(errorDetectionInterval);
        }, 300000);
      };

      // Catch security errors when Tap tries to navigate parent window to external payment URLs
      // Extract the URL from the error message and redirect the top-level window
      let redirectHandled = false;
      
      const securityErrorHandler = function(event) {
        // Log every error event to see if handler is being called
        console.log('🔔 Error event received:', {
          type: event.type,
          message: event.message,
          filename: event.filename,
          lineno: event.lineno,
          colno: event.colno,
          error: event.error,
          hasError: !!event.error
        });
        
        if (redirectHandled) {
          console.log('⚠️ Redirect already handled, ignoring error');
        return;
      }
      
        // Get error message from multiple sources - the error object itself has the full message
        const errorMsg = event.message || event.error?.message || event.error?.toString() || '';
        const errorStr = errorMsg.toString();
        
        // Also check error stack and filename
        const errorStack = event.error?.stack || '';
        const errorFilename = event.filename || '';
        
        // Try to get full error object as string (might contain full URL)
        // IMPORTANT: The error.message property contains the FULL URL, even if console truncates it
        let fullErrorText = [errorStr, errorStack, errorFilename].join(' ');
        
        // Try to stringify the entire error object to get all properties
        try {
          const errorObjStr = JSON.stringify(event.error || event);
          fullErrorText += ' ' + errorObjStr;
        } catch (e) {
          // Can't stringify, that's okay
        }
        
        // Also try to get all error properties - especially the message which has the full URL
        if (event.error) {
          try {
            // The error.message property should contain the full URL
            if (event.error.message && typeof event.error.message === 'string') {
              fullErrorText += ' ' + event.error.message;
            }
            
            // Check all properties
            for (const key in event.error) {
              if (event.error.hasOwnProperty(key)) {
                const value = event.error[key];
                if (typeof value === 'string' && (value.includes('acceptance') || value.includes('tap_process'))) {
                  fullErrorText += ' ' + value;
                }
              }
            }
          } catch (e) {
            // Can't iterate, that's okay
          }
        }
        
        // Also check the event itself for URL information
        if (event.target) {
          try {
            const targetHref = event.target.href || event.target.location?.href || '';
            if (targetHref) {
              fullErrorText += ' ' + targetHref;
            }
          } catch (e) {
            // Can't access, that's okay
          }
        }
        
        // Log all error details for debugging
        console.log('🔍 Security error detected:', {
          message: errorStr,
          stack: errorStack,
          filename: errorFilename,
          errorName: event.error?.name,
          errorType: event.error?.constructor?.name,
          hasError: !!event.error,
          errorMessage: event.error?.message,
          fullError: fullErrorText.substring(0, 2000) // Log first 2000 chars to see full URL
        });
        
        // Also log the raw error object if available
        if (event.error) {
          console.log('🔍 Raw error object:', event.error);
          try {
            console.log('🔍 Error object keys:', Object.keys(event.error));
          } catch (e) {
            // Can't access keys
          }
        }
        
        // Check if it's a navigation security error with a URL
        // Also check the error target - it might contain the URL
        const errorTarget = event.target?.href || event.target?.location?.href || '';
        const allErrorText = fullErrorText + ' ' + errorTarget;
        
        // Check for navigation security errors - both warnings and actual errors
        const isNavigationError = 
          allErrorText.includes('Failed to set a named property') ||
          allErrorText.includes('Unsafe attempt to initiate navigation') ||
          allErrorText.includes('permission to navigate') ||
          allErrorText.includes('SecurityError') ||
          (event.error && event.error.name === 'SecurityError');
        
        if (isNavigationError) {
          
          // Try multiple patterns to extract URL from error message
          // Error format: "Failed to set a named property 'href' on 'Location': The current window does not have permission to navigate the target frame to 'URL'."
          let urlMatch = null;
          const patterns = [
            // Primary pattern: "to 'URL'"
            /to\s+['"](https?:\/\/[^'"]+)['"]/i,
            // Alternative: "navigate ... to 'URL'"
            /navigate[^'"]*to\s+['"](https?:\/\/[^'"]+)['"]/i,
            // Direct URL patterns
            /['"](https?:\/\/[^'"]+tap_process[^'"]+)['"]/i,
            /['"](https?:\/\/[^'"]+knet[^'"]+)['"]/i,
            /['"](https?:\/\/acceptance[^'"]+)['"]/i,
            /['"](https?:\/\/[^'"]+gosell[^'"]+)['"]/i,
            // Patterns without quotes (for console errors)
            /(https?:\/\/acceptance[^\s'"]+)/i,
            /(https?:\/\/[^\s'"]+tap_process[^\s'"]+)/i,
            // More specific patterns
            /(https?:\/\/acceptance\.sandbox\.tap\.company[^\s'"]+)/i,
            /(https?:\/\/acceptance\.tap\.company[^\s'"]+)/i,
            // Pattern for full tap_process URLs with query params
            /(https?:\/\/acceptance\.sandbox\.tap\.company\/gosell\/v2\/payment\/tap_process\.aspx\?[^\s'"]+)/i,
            /(https?:\/\/acceptance\.tap\.company\/gosell\/v2\/payment\/tap_process\.aspx\?[^\s'"]+)/i
          ];
          
          // Try to extract from full error text (including target)
          // The error.message should contain the FULL URL even if console truncates it
          console.log('🔍 Attempting URL extraction from error text (length:', allErrorText.length, ')');
          
          for (const pattern of patterns) {
            urlMatch = allErrorText.match(pattern);
            if (urlMatch && urlMatch[1]) {
              console.log('✅ URL pattern matched:', pattern.toString());
              break;
            }
          }
          
          // If no match, try a more aggressive pattern that captures everything after "to '"
          if (!urlMatch) {
            const aggressivePattern = /to\s+['"]([^'"]+)/i;
            const match = allErrorText.match(aggressivePattern);
            if (match && match[1]) {
              let potentialUrl = match[1];
              // Check if it looks like a URL
              if (potentialUrl.startsWith('http://') || potentialUrl.startsWith('https://')) {
                // Try to find where the URL ends (might be truncated in console but full in error object)
                // Look for the end of the URL - it should end before the next quote or space
                const urlEndMatch = allErrorText.match(/to\s+['"](https?:\/\/[^'"]*)/i);
                if (urlEndMatch && urlEndMatch[1]) {
                  potentialUrl = urlEndMatch[1];
                  // Check if it's a tap_process URL
                  if (potentialUrl.includes('tap_process') || potentialUrl.includes('acceptance')) {
                    urlMatch = [null, potentialUrl];
                    console.log('✅ Extracted URL using aggressive pattern:', potentialUrl);
                  }
                }
              }
            }
          }
          
          // If still no match, but we detected the error pattern, try to get URL from iframe
          if (!urlMatch && allErrorText.includes('acceptance') && allErrorText.includes('tap_process')) {
            console.log('⚠️ URL truncated in error, attempting to get from iframe...');
            
            // Try to get URL from iframe location (may fail due to CORS)
            try {
              const iframeUrl = paymentIframe.contentWindow?.location?.href;
              if (iframeUrl && (
                  iframeUrl.includes('tap_process.aspx') ||
                  iframeUrl.includes('acceptance.sandbox.tap.company') ||
                  iframeUrl.includes('acceptance.tap.company')
                )) {
                console.log('🔗 Got URL from iframe location:', iframeUrl);
                urlMatch = [null, iframeUrl];
              }
            } catch (e) {
              console.debug('Cannot access iframe URL (CORS):', e.message);
            }
            
            // If still no URL, but we detected the error, we know Tap is trying to navigate
            // The URL is truncated, but we can try to get it from the iframe's attempted navigation
            // or construct a base URL and let the user complete the payment
            if (!urlMatch) {
              console.warn('⚠️ URL truncated in error message. Attempting alternative methods...');
              
              // Method 1: Try to get URL from iframe location (may work if navigation started)
              try {
                const iframeUrl = paymentIframe.contentWindow?.location?.href;
                if (iframeUrl && iframeUrl !== url && (
                    iframeUrl.includes('tap_process.aspx') ||
                    iframeUrl.includes('acceptance.sandbox.tap.company') ||
                    iframeUrl.includes('acceptance.tap.company')
                  )) {
                  console.log('🔗 Got URL from iframe location (after navigation attempt):', iframeUrl);
                  if (!redirectHandled) {
                    redirectHandled = true;
                    window.removeEventListener('error', securityErrorHandler, true);
                    
          if (window.top && window.top !== window) {
                      window.top.location.href = iframeUrl;
          } else {
                      window.location.href = iframeUrl;
                    }
                  }
        return;
                }
              } catch (e) {
                console.debug('Cannot access iframe URL (CORS):', e.message);
              }
              
              // Method 2: Extract partial URL and try to use it
              // The partial URL might still work if Tap's server can handle it
              const partialMatch = allErrorText.match(/(https?:\/\/acceptance[^\s'"]+)/i);
              if (partialMatch && partialMatch[1] && !redirectHandled) {
                let partialUrl = partialMatch[1];
                // Remove trailing ellipsis or special characters
                partialUrl = partialUrl.replace(/[…\.]+$/, '').trim();
                
                console.warn('⚠️ Using partial URL (may not work):', partialUrl);
                redirectHandled = true;
                window.removeEventListener('error', securityErrorHandler, true);
                
                // Try redirecting - Tap's server might redirect to the full URL
                try {
                  if (window.top && window.top !== window) {
                    window.top.location.href = partialUrl;
                  } else {
                    window.location.href = partialUrl;
                  }
                } catch (e) {
                  console.error('Failed to redirect to partial URL:', e);
                }
                return;
              }
              
              // Method 3: Try to fetch the payment URL from Tap API using charge ID
              if (window.currentTapCharge && window.currentTapCharge.id && !redirectHandled) {
                console.log('🔄 Attempting to fetch payment URL from Tap API...');
                const chargeId = window.currentTapCharge.id;
                const locationId = window.tapChargeData?.locationId || paymentData?.locationId || '';
                
                // Fetch charge status which might contain the payment URL
                fetch(`/charge/status?tap_id=${chargeId}&locationId=${locationId}`)
                  .then(response => response.json())
                  .then(data => {
                    if (data.success && data.charge) {
                      // Check if charge has transaction URL
                      const paymentUrl = data.charge.transaction?.url || 
                                       data.charge.url ||
                                       data.charge.redirect?.url;
                      
                      if (paymentUrl && (
                          paymentUrl.includes('tap_process.aspx') ||
                          paymentUrl.includes('acceptance.sandbox.tap.company') ||
                          paymentUrl.includes('acceptance.tap.company') ||
                          paymentUrl.includes('/gosell/v2/payment/') ||
                          paymentUrl.includes('knet')
                        )) {
                        console.log('🔗 Got payment URL from API:', paymentUrl);
                        redirectHandled = true;
                        window.removeEventListener('error', securityErrorHandler, true);
                        
                        if (window.top && window.top !== window) {
                          window.top.location.href = paymentUrl;
                        } else {
                          window.location.href = paymentUrl;
                        }
                      } else {
                        console.warn('⚠️ API response does not contain external payment URL');
                      }
                    }
                  })
                  .catch(error => {
                    console.error('❌ Failed to fetch payment URL from API:', error);
                  });
              } else {
                console.warn('⚠️ Could not extract URL. Error details logged above.');
              }
            }
          }
          
          if (urlMatch && urlMatch[1]) {
            let redirectUrl = urlMatch[1];
            // Clean up URL (remove trailing quotes or other characters)
            redirectUrl = redirectUrl.replace(/['"]+$/, '').trim();
            // Remove any trailing dots or ellipsis
            redirectUrl = redirectUrl.replace(/\.+$/, '');
            
            console.log('🔗 Extracted redirect URL from security error:', redirectUrl);
            
            // Check if it's an external payment URL
            const isExternalPaymentUrl = 
              redirectUrl.includes('knet') ||
              redirectUrl.includes('tap_process.aspx') ||
              redirectUrl.includes('acceptance.sandbox.tap.company') ||
              redirectUrl.includes('acceptance.tap.company') ||
              redirectUrl.includes('/gosell/v2/payment/');
            
            if (isExternalPaymentUrl) {
              redirectHandled = true;
              console.log('🔗 Redirecting top-level window to external payment URL:', redirectUrl);
              window.removeEventListener('error', securityErrorHandler, true);
              
              // Redirect the top-level window (breaks out of iframe)
              try {
                if (window.top && window.top !== window) {
                  window.top.location.href = redirectUrl;
                } else {
                  window.location.href = redirectUrl;
                }
              } catch (e) {
                console.error('Failed to redirect:', e);
                // Fallback: try to open in new window
                window.open(redirectUrl, '_top');
              }
            }
          } else {
            console.warn('⚠️ Could not extract URL from error message. Full error:', fullErrorText);
          }
        }
      };
      
      // Intercept console errors to catch navigation errors
      // Chain with existing console.error interceptor
      // Get the ORIGINAL console.error (before any other interceptors)
      const originalConsoleError = console.error.bind(console);
      
      // Check if console.error has already been intercepted
      let existingConsoleError = console.error;
      if (console.error.toString().includes('consoleErrorInterceptor') || 
          console.error.toString().includes('originalConsoleError')) {
        // Already intercepted, use the current one
        existingConsoleError = console.error;
      } else {
        // Not intercepted yet, use original
        existingConsoleError = originalConsoleError;
      }
      
      const consoleErrorInterceptor = function(...args) {
        const errorText = args.join(' ');
        
        // Log ALL console errors to see what we're getting
        console.log('📢 Console.error called:', errorText.substring(0, 200));
        
        // Check if it's a navigation security error
        if (errorText.includes('Failed to set a named property') ||
            errorText.includes('Unsafe attempt to initiate navigation') ||
            errorText.includes('permission to navigate') ||
            errorText.includes('SecurityError') ||
            (args[0] && args[0].name === 'SecurityError')) {
          
          console.log('🔍 Console error with navigation issue detected!', errorText);
          
          // Try multiple patterns to extract URL
          let urlMatch = null;
          const patterns = [
            /to\s+['"](https?:\/\/[^'"]+)['"]/i,
            /navigate[^'"]*to\s+['"](https?:\/\/[^'"]+)['"]/i,
            /['"](https?:\/\/[^'"]+tap_process[^'"]+)['"]/i,
            /['"](https?:\/\/[^'"]+knet[^'"]+)['"]/i,
            /['"](https?:\/\/acceptance[^'"]+)['"]/i,
            /['"](https?:\/\/[^'"]+gosell[^'"]+)['"]/i,
            /(https?:\/\/acceptance[^\s'"]+)/i,
            /(https?:\/\/[^\s'"]+tap_process[^\s'"]+)/i
          ];
          
          for (const pattern of patterns) {
            urlMatch = errorText.match(pattern);
            if (urlMatch && urlMatch[1]) {
              break;
            }
          }
          
          if (urlMatch && urlMatch[1]) {
            let redirectUrl = urlMatch[1];
            // Clean up URL
            redirectUrl = redirectUrl.replace(/['"]+$/, '').trim();
            
            console.log('🔗 Extracted redirect URL from console error:', redirectUrl);
            
            const isExternalPaymentUrl = 
              redirectUrl.includes('knet') ||
              redirectUrl.includes('tap_process.aspx') ||
              redirectUrl.includes('acceptance.sandbox.tap.company') ||
              redirectUrl.includes('acceptance.tap.company') ||
              redirectUrl.includes('/gosell/v2/payment/');
            
            if (isExternalPaymentUrl && !redirectHandled) {
              redirectHandled = true;
              console.log('🔗 Redirecting top-level window to external payment URL:', redirectUrl);
              console.error = existingConsoleError; // Restore to existing interceptor
              window.removeEventListener('error', securityErrorHandler, true);
              window.removeEventListener('unhandledrejection', rejectionHandler);
              
              try {
          if (window.top && window.top !== window) {
                  window.top.location.href = redirectUrl;
          } else {
                  window.location.href = redirectUrl;
                }
              } catch (e) {
                console.error('Failed to redirect:', e);
                window.open(redirectUrl, '_top');
              }
              return; // Don't log the error
            }
          } else {
            console.warn('⚠️ Could not extract URL from console error');
          }
        }
        
        // Call existing console.error (which chains to the original)
        existingConsoleError.apply(console, args);
      };
      
      console.error = consoleErrorInterceptor;
      console.log('✅ Console.error interceptor installed for navigation errors');
      
      // Listen for security errors with capture phase
      // Use capture phase to catch errors before they bubble
      window.addEventListener('error', securityErrorHandler, true);
      
      // Also add a test log to confirm the listener is registered
      console.log('✅ Security error handler registered and listening for errors');
      
      // Also listen for unhandled promise rejections
      const rejectionHandler = function(event) {
        if (redirectHandled) return;
        
        const reason = event.reason;
        const reasonMsg = (reason?.message || reason?.toString() || '');
        
        console.log('🔍 Unhandled rejection:', reasonMsg);
        
        if (reasonMsg.includes('Failed to set a named property') ||
            reasonMsg.includes('Unsafe attempt to initiate navigation') ||
            reasonMsg.includes('permission to navigate')) {
          
          // Try multiple patterns to extract URL
          let urlMatch = null;
          const patterns = [
            /to\s+['"](https?:\/\/[^'"]+)['"]/i,
            /navigate[^'"]*to\s+['"](https?:\/\/[^'"]+)['"]/i,
            /['"](https?:\/\/[^'"]+tap_process[^'"]+)['"]/i,
            /['"](https?:\/\/[^'"]+knet[^'"]+)['"]/i,
            /['"](https?:\/\/acceptance[^'"]+)['"]/i,
            /['"](https?:\/\/[^'"]+gosell[^'"]+)['"]/i,
            /(https?:\/\/acceptance[^\s'"]+)/i,
            /(https?:\/\/[^\s'"]+tap_process[^\s'"]+)/i
          ];
          
          for (const pattern of patterns) {
            urlMatch = reasonMsg.match(pattern);
            if (urlMatch && urlMatch[1]) {
              break;
            }
          }
          
          if (urlMatch && urlMatch[1]) {
            let redirectUrl = urlMatch[1];
            redirectUrl = redirectUrl.replace(/['"]+$/, '').trim();
            
            console.log('🔗 Extracted redirect URL from rejection:', redirectUrl);
            
            const isExternalPaymentUrl = 
              redirectUrl.includes('knet') ||
              redirectUrl.includes('tap_process.aspx') ||
              redirectUrl.includes('acceptance.sandbox.tap.company') ||
              redirectUrl.includes('acceptance.tap.company') ||
              redirectUrl.includes('/gosell/v2/payment/');
            
            if (isExternalPaymentUrl && !redirectHandled) {
              redirectHandled = true;
              console.log('🔗 Redirecting top-level window to external payment URL:', redirectUrl);
              window.removeEventListener('unhandledrejection', rejectionHandler);
              
              try {
                if (window.top && window.top !== window) {
                  window.top.location.href = redirectUrl;
                } else {
                  window.location.href = redirectUrl;
                }
              } catch (e) {
                console.error('Failed to redirect:', e);
                window.open(redirectUrl, '_top');
              }
            }
          }
        }
      };
      
      window.addEventListener('unhandledrejection', rejectionHandler);
      
      // Store reference to existing console.error for cleanup
      const existingConsoleErrorForCleanup = existingConsoleError;
      
      // Cleanup error handlers when payment completes
      const cleanupErrorHandlers = function() {
        console.error = existingConsoleErrorForCleanup; // Restore to existing interceptor
        window.removeEventListener('error', securityErrorHandler, true);
        window.removeEventListener('unhandledrejection', rejectionHandler);
      };
      
      // Cleanup after 10 minutes
      setTimeout(cleanupErrorHandlers, 600000);

      // Also listen for postMessage from Tap that might indicate navigation
      const tapMessageHandler = function(event) {
        // Tap might send messages about navigation
        if (event.origin && (
            event.origin.includes('checkout.tap.company') ||
            event.origin.includes('tap.company')
          )) {
          try {
            const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
            
            // Check if Tap is trying to communicate a redirect URL
            if (data && (data.redirectUrl || data.url || data.paymentUrl)) {
              const redirectUrl = data.redirectUrl || data.url || data.paymentUrl;
              console.log('📥 Received redirect URL from Tap:', redirectUrl);
              
              // Check if it's an external payment URL
              const isExternalPaymentUrl = 
                redirectUrl.includes('knet') ||
                redirectUrl.includes('tap_process.aspx') ||
                redirectUrl.includes('acceptance.sandbox.tap.company') ||
                redirectUrl.includes('acceptance.tap.company') ||
                redirectUrl.includes('/gosell/v2/payment/');
              
              if (isExternalPaymentUrl) {
                console.log('🔗 Redirecting top-level window to external payment URL');
                clearInterval(navigationMonitor);
                window.removeEventListener('message', tapMessageHandler);
                
                // Redirect the top-level window
          if (window.top && window.top !== window) {
                  window.top.location.href = redirectUrl;
          } else {
                  window.location.href = redirectUrl;
                }
              }
            }
          } catch (e) {
            // Not a JSON message or not a redirect message
          }
        }
      };
      
      window.addEventListener('message', tapMessageHandler);
      
      // Cleanup handlers when payment completes
      const originalMessageHandler = messageHandler;
      const wrappedMessageHandler = function(event) {
        console.error = existingConsoleError; // Restore to existing interceptor
        window.removeEventListener('message', tapMessageHandler);
        window.removeEventListener('error', securityErrorHandler, true);
        window.removeEventListener('unhandledrejection', rejectionHandler);
        originalMessageHandler(event);
      };
      
      // Replace the original message handler
      window.removeEventListener('message', messageHandler);
      window.addEventListener('message', wrappedMessageHandler);
    }

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🚀 Charge API Integration Loaded Successfully');
      
      console.log('🔍 Browser info:', {
        userAgent: navigator.userAgent,
        platform: navigator.platform,
        vendor: navigator.vendor,
        hasTouch: 'ontouchend' in document
      });
      
      console.log('🔍 Window context:', {
        isIframe: window !== window.top,
        parentExists: window.parent !== window,
        topExists: window.top !== window
      });
      
      // Send ready event immediately
      sendReadyEvent();

      // Listen for visibility changes and resend ready event when page becomes visible
      // This helps if GHL reloads or the iframe becomes visible again
      document.addEventListener('visibilitychange', function() {
        if (!document.hidden && !ghlAcknowledged) {
          console.log('👁️ Page became visible - resending ready event');
          readyEventRetryCount = 0; // Reset counter
          sendReadyEvent();
        }
      });

      // Also listen for focus events (additional safeguard)
      window.addEventListener('focus', function() {
        if (!ghlAcknowledged) {
          console.log('🎯 Window focused - resending ready event');
          readyEventRetryCount = 0; // Reset counter
          sendReadyEvent();
        }
      });

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



  </script>
</body>
</html>
