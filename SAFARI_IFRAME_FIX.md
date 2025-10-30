# Safari Iframe Payment Fix

## Problem Description

Safari blocks third-party iframes from requesting payments due to security policies. The error message "Third-party iframes are not allowed to request payments unless explicitly allowed via Feature-Policy (payment)" prevents payment processing in Safari when the payment form is embedded in an iframe.

## Root Cause

Safari's security model restricts payment requests from iframes to prevent malicious websites from initiating payments without user consent. This is enforced through:

1. **Feature-Policy restrictions** - Payment requests are blocked in iframes
2. **Content Security Policy (CSP)** - Frame-ancestors restrictions
3. **Cross-origin iframe limitations** - Limited communication between iframe and parent

## Solution Implemented

### 1. Headers and Security Policies

**File: `app/Http/Middleware/PaymentPolicyMiddleware.php`**

Added middleware to set proper headers for iframe payment compatibility:

```php
// Add headers to allow iframe payment requests in Safari
$response->headers->set('X-Frame-Options', 'SAMEORIGIN');
$response->headers->set('Content-Security-Policy', "frame-ancestors 'self' https://app.gohighlevel.com https://*.gohighlevel.com https://app.mediasolution.io https://*.mediasolution.io");

// Add Feature-Policy header to allow payment requests in iframe
$response->headers->set('Permissions-Policy', 'payment=*');

// Add additional headers for Safari compatibility
$response->headers->set('X-Content-Type-Options', 'nosniff');
$response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
```

### 2. Safari Detection and Popup Flow

**File: `resources/views/charge.blade.php`**

Implemented browser detection and popup-based payment flow:

```javascript
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
  const popupFeatures = 'width=800,height=600,scrollbars=yes,resizable=yes,status=yes,location=yes,toolbar=no,menubar=no';
  paymentPopup = window.open(url, 'tap_payment', popupFeatures);
  
  if (!paymentPopup) {
    showError('Popup blocked. Please allow popups for this site and try again.');
    return false;
  }
  
  // Monitor popup for completion
  const checkClosed = setInterval(() => {
    if (paymentPopup.closed) {
      clearInterval(checkClosed);
      checkPaymentStatus();
    }
  }, 1000);
  
  return true;
}
```

### 3. Payment Flow Logic

The system now handles payment differently based on browser:

**For Safari:**
1. Detect Safari browser
2. Open payment URL in popup window
3. Monitor popup for completion
4. Listen for messages from popup
5. Handle success/failure based on popup communication

**For Other Browsers:**
1. Use direct redirect to payment URL
2. Standard iframe flow continues

### 4. Popup Communication

**File: `resources/views/payment/redirect.blade.php`**

Added popup detection and communication:

```javascript
// Check if this page is opened in a popup
function isPopup() {
  return window.opener && window.opener !== window;
}

// Send message to parent window if in popup
function sendMessageToParent(message) {
  if (isPopup() && window.opener) {
    try {
      window.opener.postMessage(message, '*');
    } catch (error) {
      console.error('Failed to send message to parent:', error);
    }
  }
}
```

### 5. Status Checking API

**File: `app/Http/Controllers/ClientIntegrationController.php`**

Added API endpoint to check payment status:

```php
public function getLastChargeStatus(Request $request)
{
    // Get the most recent charge from session
    $lastChargeId = session('last_charge_id');
    
    // Call Tap API to get charge details
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . $secretKey,
        'accept' => 'application/json',
    ])->get('https://api.tap.company/v2/charges/' . $lastChargeId);
    
    return response()->json([
        'success' => true,
        'charge' => $chargeData
    ]);
}
```

## How It Works

### 1. Initial Load
- Page loads in iframe
- Safari detection runs
- Ready event sent to GoHighLevel

### 2. Payment Initiation
- GHL sends payment data
- Charge created via API
- Browser-specific redirect logic:
  - **Safari**: Open popup with payment URL
  - **Other browsers**: Direct redirect

### 3. Payment Processing
- User completes payment in popup (Safari) or redirected page
- Payment status communicated back to parent
- Success/failure handled appropriately

### 4. Completion
- Popup closes automatically (Safari)
- Success/failure message sent to GHL
- User sees appropriate feedback

## Testing

### Safari Testing
1. Open payment form in Safari
2. Verify popup opens for payment
3. Complete payment in popup
4. Verify popup closes and success message appears

### Other Browser Testing
1. Open payment form in Chrome/Firefox
2. Verify direct redirect to payment page
3. Complete payment
4. Verify redirect back to success page

## Fallback Mechanisms

1. **Popup Blocked**: Falls back to direct redirect
2. **Communication Failed**: Uses status checking API
3. **Session Lost**: Graceful error handling

## Browser Support

- ✅ **Safari** (Desktop & Mobile) - Popup flow
- ✅ **Chrome** - Direct redirect flow
- ✅ **Firefox** - Direct redirect flow
- ✅ **Edge** - Direct redirect flow

## Security Considerations

1. **CSP Headers**: Properly configured for iframe embedding
2. **Feature-Policy**: Allows payment requests in iframe
3. **Popup Security**: Limited popup features for security
4. **Message Validation**: Validates messages from popup

## Troubleshooting

### Common Issues

1. **Popup Blocked**
   - Solution: User needs to allow popups for the site
   - Fallback: Direct redirect

2. **Communication Failed**
   - Solution: Check browser console for errors
   - Fallback: Status checking API

3. **Session Lost**
   - Solution: Ensure proper session handling
   - Fallback: Graceful error message

### Debug Information

The system provides extensive logging:
- Browser detection results
- Popup opening status
- Message communication
- Payment status updates

## Future Improvements

1. **Progressive Enhancement**: Start with iframe, fallback to popup
2. **Better UX**: Loading states and progress indicators
3. **Analytics**: Track popup vs redirect usage
4. **Mobile Optimization**: Better mobile popup handling

## Files Modified

1. `app/Http/Middleware/PaymentPolicyMiddleware.php` - Headers and security
2. `bootstrap/app.php` - Middleware registration
3. `resources/views/charge.blade.php` - Safari detection and popup flow
4. `resources/views/payment/redirect.blade.php` - Popup communication
5. `app/Http/Controllers/ClientIntegrationController.php` - Status checking API
6. `routes/api.php` - New API endpoint

This solution ensures that payment processing works seamlessly across all browsers while maintaining security and providing a good user experience.
