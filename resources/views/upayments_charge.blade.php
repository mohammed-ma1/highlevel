{{-- 
  resources/views/upayments_charge.blade.php
  
  GoHighLevel Payment UI for UPayments (hosted checkout link)
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
    body { margin: 0; padding: 0; background: transparent; font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    .payment-container { background: transparent; max-width: 100%; width: 100%; position: relative; }
    .payment-body { padding: 0; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
    .popup-payment-content {
      text-align: center;
      max-width: 520px;
      width: 100%;
      background: white;
      border-radius: 24px;
      padding: 56px 40px;
      box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
      border: 1px solid #e5e7eb;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 28px;
      box-sizing: border-box;
    }
    .payment-loading-spinner {
      width: 50px;
      height: 50px;
      border: 4px solid #e5e7eb;
      border-top: 4px solid #2563eb;
      border-radius: 50%;
      animation: spin 1s linear infinite;
    }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    .proceed-payment-button {
      background: #2563eb;
      color: white;
      border: none;
      padding: 16px 28px;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      max-width: 420px;
      box-sizing: border-box;
    }
    .proceed-payment-button:hover { background: #1d4ed8; transform: translateY(-1px); }
    .error-box {
      width: 100%;
      max-width: 520px;
      background: #fef2f2;
      border: 1px solid #fecaca;
      color: #b91c1c;
      padding: 12px 16px;
      border-radius: 10px;
      font-size: 14px;
      display: none;
      box-sizing: border-box;
    }
  </style>
</head>
<body>
  <div class="payment-container">
    <div class="payment-body" id="payment-body">
      <div class="popup-payment-content">
        <div class="payment-loading-spinner"></div>
        <div style="font-weight: 700; font-size: 18px; color: #0f172a;">Preparing your checkout…</div>
        <div class="error-box" id="error-box"></div>
        <button class="proceed-payment-button" id="proceed-btn" type="button" style="display:none;">
          <span>Go to payment</span>
          <span aria-hidden="true">→</span>
        </button>
      </div>
    </div>
  </div>

  <script>
    let paymentData = null;
    let checkoutUrl = null;
    let paymentPopup = null;
    let ghlAcknowledged = false;
    let readyRetry = null;
    let readyCount = 0;
    const MAX_READY_RETRY = 20;

    function showError(message) {
      const box = document.getElementById('error-box');
      box.textContent = message;
      box.style.display = 'block';
    }

    function detectSafari() {
      const ua = navigator.userAgent;
      if (/Edg/.test(ua) || /OPR/.test(ua) || /Opera/.test(ua) || /Firefox/.test(ua)) return false;
      if (/Chrome/.test(ua) && !/Chromium/.test(ua)) return false;
      const isIOS = /iPad|iPhone|iPod/.test(ua);
      if (isIOS) return true;
      const isMac = /Macintosh/.test(ua);
      const hasSafari = /Safari/.test(ua);
      const noChrome = !/Chrome/.test(ua) && !/Chromium/.test(ua);
      return isMac && hasSafari && noChrome;
    }

    function sendMessageToGHL(message) {
      // Same multi-channel approach as Tap redirect (opener/parent/top + localStorage fallback)
      let sent = false;
      try {
        if (window.opener && window.opener !== window) {
          window.opener.postMessage(JSON.stringify(message), '*');
          sent = true;
          return;
        }
      } catch (e) {}

      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(message), '*');
          sent = true;
        }
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(message), '*');
          sent = true;
        }
      } catch (e) {}

      if (!sent || window.parent === window) {
        try {
          const key = 'ghl_payment_message_' + Date.now();
          localStorage.setItem(key, JSON.stringify({ message, timestamp: Date.now(), source: 'payment_redirect' }));
          setTimeout(() => { try { localStorage.removeItem(key); } catch(e) {} }, 1000);
        } catch (e) {}
      }
    }

    function sendReadyEvent(force = false) {
      if (ghlAcknowledged && !force) return;
      const readyEvent = {
        type: 'custom_provider_ready',
        loaded: true,
        addCardOnFileSupported: false,
        version: '1.0',
        capabilities: ['payment'],
        supportedModes: ['payment']
      };
      try {
        if (window.parent && window.parent !== window) {
          window.parent.postMessage(JSON.stringify(readyEvent), '*');
        }
        if (window.top && window.top !== window && window.top !== window.parent) {
          window.top.postMessage(JSON.stringify(readyEvent), '*');
        }
      } catch (e) {}

      readyCount++;
      if (!ghlAcknowledged && readyCount < MAX_READY_RETRY && !readyRetry) {
        readyRetry = setInterval(() => {
          if (!ghlAcknowledged && readyCount < MAX_READY_RETRY) {
            sendReadyEvent(true);
          } else {
            clearInterval(readyRetry);
            readyRetry = null;
          }
        }, 500);
      }
    }

    function isValidGHLMessage(data) {
      if (!data || typeof data !== 'object') return false;
      if (data.type !== 'payment_initiate_props') return false;
      return !!(data.amount && data.currency && data.orderId && data.transactionId && data.locationId);
    }

    async function createUPaymentsCharge() {
      try {
        const resp = await fetch('/api/charge/create-upayment', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
          },
          body: JSON.stringify({
            amount: paymentData.amount,
            currency: paymentData.currency,
            orderId: paymentData.orderId,
            transactionId: paymentData.transactionId,
            locationId: paymentData.locationId,
            liveMode: !!paymentData.liveMode,
            contact: paymentData.contact || null,
            description: `Payment for ${paymentData.productDetails?.productId || 'order'}`,
            customerExtraData: ''
          })
        });

        const result = await resp.json().catch(() => ({}));
        if (!resp.ok || !result.success || !result.link) {
          throw new Error(result.message || 'Failed to create charge with UPayments');
        }

        checkoutUrl = result.link;

        // Default behavior: open checkout in new tab. Safari often requires explicit user gesture, so show button + popup.
        const shouldUsePopup = detectSafari();
        if (shouldUsePopup) {
          const btn = document.getElementById('proceed-btn');
          btn.style.display = 'inline-flex';
          btn.onclick = function() {
            const features = 'width=900,height=700,scrollbars=yes,resizable=yes,status=yes,location=yes,toolbar=no,menubar=no';
            paymentPopup = window.open(checkoutUrl, 'upayments_payment', features);
            if (!paymentPopup) {
              window.location.href = checkoutUrl;
              return;
            }
            const checkClosed = setInterval(() => {
              if (paymentPopup.closed) {
                clearInterval(checkClosed);
                sendMessageToGHL({ type: 'custom_element_close_response' });
              }
            }, 1000);
            window.paymentPopupCheckInterval = checkClosed;
          };
        } else {
          const newTab = window.open(checkoutUrl, '_blank');
          if (!newTab) {
            window.location.href = checkoutUrl;
          }
        }
      } catch (e) {
        showError(e.message || 'Unable to create checkout link');
        sendMessageToGHL({
          type: 'custom_element_error_response',
          error: { description: e.message || 'Payment initialization failed' }
        });
      }
    }

    window.addEventListener('message', function(event) {
      let parsed = event.data;
      if (typeof parsed === 'string') {
        try { parsed = JSON.parse(parsed); } catch (e) { return; }
      }
      if (!parsed) return;

      if (parsed.type === 'payment_initiate_props') {
        ghlAcknowledged = true;
      }

      if (!isValidGHLMessage(parsed)) return;
      paymentData = parsed;
      createUPaymentsCharge();
    });

    // If we receive close/success/error from a redirect page in popup/new tab, relay to GHL and close popup.
    window.addEventListener('message', function(event) {
      let parsed = event.data;
      if (typeof parsed === 'string') {
        try { parsed = JSON.parse(parsed); } catch (e) { return; }
      }
      if (!parsed || typeof parsed !== 'object') return;
      const types = ['custom_element_success_response', 'custom_element_error_response', 'custom_element_close_response'];
      if (!types.includes(parsed.type)) return;

      if (paymentPopup && !paymentPopup.closed) {
        try { paymentPopup.close(); } catch(e) {}
        if (window.paymentPopupCheckInterval) {
          clearInterval(window.paymentPopupCheckInterval);
          window.paymentPopupCheckInterval = null;
        }
      }

      sendMessageToGHL(parsed);
    });

    // localStorage relay (new-tab scenario)
    window.addEventListener('storage', function(event) {
      try {
        if (event.key && event.key.startsWith('ghl_payment_message_') && event.newValue) {
          const data = JSON.parse(event.newValue);
          if (data.source === 'payment_redirect' && data.message) {
            sendMessageToGHL(data.message);
          }
        }
      } catch (e) {}
    });

    document.addEventListener('DOMContentLoaded', function() {
      sendReadyEvent();
      setTimeout(() => sendReadyEvent(true), 500);
    });
  </script>
</body>
</html>

