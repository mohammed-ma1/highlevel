{{-- resources/views/tap.blade.php --}}
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
    // Pull SDK helpers
    const { renderTapCard, Theme, Currencies, Direction, Edges, Locale, tokenize } = window.CardSDK;

    // GoHighLevel iframe communication
    let paymentData = null;
    let isReady = false;

    // Listen for messages from GoHighLevel parent window
    window.addEventListener('message', function(event) {
      try {
        console.log('Received message from parent:', event.data);
        
        if (event.data && event.data.type === 'payment_initiate_props') {
          paymentData = event.data;
          console.log('Payment data received:', paymentData);
          console.log('Amount from GoHighLevel:', paymentData.amount, paymentData.currency);
          updatePaymentForm(paymentData);
        } else if (event.data && event.data.type === 'setup_initiate_props') {
          paymentData = event.data;
          console.log('Setup data received:', paymentData);
          updatePaymentForm(paymentData);
        }
      } catch (error) {
        console.log('Error processing message from parent:', error);
        // Ignore parsing errors from other extensions or scripts
      }
    });

    // Send ready event to GoHighLevel parent window
    function sendReadyEvent() {
      const readyEvent = {
        type: 'custom_provider_ready',
        loaded: true,
        addCardOnFileSupported: true // We support adding cards on file
      };
      
      console.log('Sending ready event:', readyEvent);
      window.parent.postMessage(readyEvent, '*');
      isReady = true;
    }

    // Update payment form with data from GoHighLevel
    function updatePaymentForm(data) {
      console.log('Updating payment form with data:', data);
      
      // Update amount display
      if (data.amount && data.currency) {
        const amountDisplay = document.getElementById('amount-display');
        if (amountDisplay) {
          amountDisplay.textContent = data.amount + ' ' + data.currency;
          console.log('Amount updated to:', data.amount + ' ' + data.currency);
        }
      }
      
      // Update customer info
      if (data.contact) {
        console.log('Customer info:', data.contact);
        
        // Update customer details in the form if needed
        if (data.contact.name) {
          console.log('Customer name:', data.contact.name);
        }
        if (data.contact.email) {
          console.log('Customer email:', data.contact.email);
        }
      }
      
      // Update publishable key if provided
      if (data.publishableKey) {
        console.log('New publishable key received:', data.publishableKey);
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
          console.log('Tap card configuration updated with amount:', data.amount, 'currency:', data.currency);
        } catch (error) {
          console.log('Could not update Tap card configuration:', error);
        }
      }
    }

    // Send success response to GoHighLevel
    function sendSuccessResponse(chargeId) {
      const successEvent = {
        type: 'custom_element_success_response',
        chargeId: chargeId
      };
      
      console.log('Sending success response:', successEvent);
      window.parent.postMessage(successEvent, '*');
    }

    // Send error response to GoHighLevel
    function sendErrorResponse(errorMessage) {
      const errorEvent = {
        type: 'custom_element_error_response',
        error: {
          description: errorMessage
        }
      };
      
      console.log('Sending error response:', errorEvent);
      window.parent.postMessage(errorEvent, '*');
    }

    // Send close response to GoHighLevel
    function sendCloseResponse() {
      const closeEvent = {
        type: 'custom_element_close_response'
      };
      
      console.log('Sending close response:', closeEvent);
      window.parent.postMessage(closeEvent, '*');
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
        publicKey: paymentData?.publishableKey || '{{ $publishableKey ?? "pk_test_xItqaSsJzl5g2K08fCwYbMvQ" }}',
        merchant: {
          id: '{{ $merchantId ?? "61000786" }}'
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
        showSuccess('🎉 Payment tokenized successfully! Token: ' + data.id);
        showResult(data);

        // Send success response to GoHighLevel
        sendSuccessResponse(data.id);

        // Example: POST token to your Laravel backend for creating a charge
        // fetch("{{ route('client.webhook') }}", {
        //   method: "POST",
        //   headers: {
        //     "Content-Type": "application/json",
        //     "X-CSRF-TOKEN": "{{ csrf_token() }}"
        //   },
        //   body: JSON.stringify({ token: data?.id })
        // }).then(r => r.json()).then(console.log).catch(console.error);
      }
    });
    }

    // Initialize Tap Card when page loads
    initializeTapCard();

    // Send ready event after a short delay to ensure iframe is loaded
    setTimeout(() => {
      sendReadyEvent();
    }, 1000);

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
