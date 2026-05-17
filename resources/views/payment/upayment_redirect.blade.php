{{-- 
  resources/views/payment/upayment_redirect.blade.php
  
  Payment redirect handler for UPayments hosted checkout (Non-Whitelabel).
  UPayments appends `track_id` and `result` to returnUrl/cancelUrl.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Processing Payment...</title>
  <style>
    body { margin: 0; padding: 0; background: transparent; }
  </style>
</head>
<body>
  <script>
    function getUrlParams() {
      const params = new URLSearchParams(window.location.search);
      return {
        track_id: params.get('track_id') || params.get('trackId'),
        result: params.get('result'),
        locationId: params.get('locationId'),
        cancel: params.get('cancel'),
        transactionId: params.get('transactionId'),
        orderId: params.get('orderId')
      };
    }

    function sendMessageToPlatform(message) {
      try {
        let sent = false;

        if (window.opener && window.opener !== window) {
          try {
            window.opener.postMessage(JSON.stringify(message), '*');
            sent = true;
            return;
          } catch (e) {}
        }

        if (window.parent && window.parent !== window) {
          try {
            window.parent.postMessage(JSON.stringify(message), '*');
            sent = true;
          } catch (e) {}
        }

        if (window.top && window.top !== window && window.top !== window.parent) {
          try {
            window.top.postMessage(JSON.stringify(message), '*');
            sent = true;
          } catch (e) {}
        }

        if (!sent || window.parent === window) {
          try {
            const storageKey = 'upayments_payment_message_' + Date.now();
            localStorage.setItem(storageKey, JSON.stringify({
              message,
              timestamp: Date.now(),
              source: 'payment_redirect'
            }));
            setTimeout(() => { try { localStorage.removeItem(storageKey); } catch(e) {} }, 1000);
          } catch (e) {}
        }
      } catch (e) {}
    }

    function sendSuccessToPlatform(chargeId) {
      sendMessageToPlatform({ type: 'custom_element_success_response', chargeId });
    }

    function sendErrorToPlatform(errorMessage) {
      sendMessageToPlatform({
        type: 'custom_element_error_response',
        error: { description: errorMessage }
      });
    }

    function sendCloseToPlatform() {
      sendMessageToPlatform({ type: 'custom_element_close_response' });
    }

    function mapResultToState(result) {
      const r = (result || '').toUpperCase();
      if (!r) return 'unknown';
      if (r.includes('NOT CAPTURE') || r.includes('NOT_CAPTURE')) return 'failed';
      if (r.includes('CAPTURE') || r.includes('SUCCESS') || r.includes('PAID') || r === 'DONE') return 'succeeded';
      if (r.includes('FAIL') || r.includes('DECLIN') || r.includes('CANCEL') || r.includes('ERROR') || r.includes('NOT ')) return 'failed';
      if (r.includes('PENDING') || r.includes('INIT') || r.includes('PROCESS')) return 'pending';
      return 'unknown';
    }

    async function fetchStatus(trackId, locationId) {
      const qs = new URLSearchParams({ track_id: trackId, locationId });
      const resp = await fetch(`/api/upayment/status?${qs.toString()}`, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
      });
      if (!resp.ok) {
        throw new Error('Status API failed');
      }
      return await resp.json();
    }

    async function processPayment() {
      const params = getUrlParams();

      // Explicit cancel flag or missing track_id → treat as close/error.
      if (params.cancel === 'true') {
        sendCloseToPlatform();
        return;
      }

      const trackId = params.track_id;
      const result = params.result;

      // Fast-path based on result param (if present).
      const stateFromResult = mapResultToState(result);
      if (trackId && stateFromResult === 'succeeded') {
        sendSuccessToPlatform(trackId);
        return;
      }
      if (stateFromResult === 'failed') {
        sendErrorToPlatform('Payment failed');
        return;
      }

      // If we have trackId + locationId, ask backend for authoritative status.
      if (trackId && params.locationId) {
        try {
          const status = await fetchStatus(trackId, params.locationId);
          if (status.success && status.state === 'succeeded') {
            sendSuccessToPlatform(trackId);
          } else if (status.success && status.state === 'failed') {
            sendErrorToPlatform('Payment failed');
          } else if (status.success && status.state === 'pending') {
            // Pending: do nothing aggressive; treat as close so the platform can continue polling/verify.
            sendCloseToPlatform();
          } else {
            sendErrorToPlatform(status.message || 'Unable to verify payment status');
          }
          return;
        } catch (e) {
          // fall through
        }
      }

      // If we have trackId but couldn't verify, treat as error so the platform can re-verify
      // via the queryUrl independently. Never assume success without confirmation.
      if (trackId) {
        sendErrorToPlatform('Payment status could not be verified. Please check your order status.');
        return;
      }

      sendErrorToPlatform('No payment information found in the redirect URL.');
    }

    document.addEventListener('DOMContentLoaded', function() {
      processPayment();
    });
  </script>
</body>
</html>

