# GoHighLevel Integration Troubleshooting Guide

## 🔍 **Current Issue Analysis**

Based on your console output, the problem is clear:

### ✅ **What's Working:**
1. Your iframe loads correctly
2. Ready event is sent: `custom_provider_ready`
3. Your iframe is listening for GHL messages
4. Backend verification endpoint exists: `/payment/query`

### ❌ **What's Missing:**
**GHL is not sending the `payment_initiate_props` data to your iframe**

## 🚨 **Root Cause**

The issue is in your **GHL integration configuration**. GHL is not sending payment data because:

1. **Payment URL not configured correctly in GHL**
2. **API keys not set up in GHL**
3. **Integration not activated in GHL**
4. **Wrong GHL environment (test vs live)**

## 🔧 **How to Fix the GHL Integration**

### **Step 1: Check GHL Integration Configuration**

In your GoHighLevel dashboard:

1. **Go to Settings → Integrations → Custom Payment Provider**
2. **Verify Payment URL**: Should be `https://dashboard.mediasolution.io/tap`
3. **Check API Keys**: Ensure your Tap API keys are configured
4. **Verify Integration Status**: Make sure it's activated

### **Step 2: Test the Integration**

1. **Create a test funnel/form in GHL**
2. **Add a payment step**
3. **Select your custom payment provider**
4. **Test the payment flow**

### **Step 3: Check GHL Environment**

Make sure you're testing in the correct environment:
- **Test Environment**: Use test API keys
- **Live Environment**: Use live API keys

## 🧪 **Testing Your Iframe**

While fixing the GHL configuration, you can test your iframe:

### **Method 1: Direct Iframe Console**
1. Navigate to: `https://app.mediasolution.io/v2/preview/hjlbiZ2niIjjWetPSvT5`
2. **Right-click on the payment form**
3. **Select "Inspect"** (this opens iframe's dev tools)
4. **Go to Console tab**
5. **Run**: `window.testGHLIntegration.testCompleteFlow()`

### **Method 2: Test Complete Flow**
```javascript
// This simulates the entire payment flow
window.testGHLIntegration.testCompleteFlow()
```

Expected output:
```
🧪 Testing Complete Payment Flow...
📋 Step 1: Simulate GHL payment data
✅ GHL Payment data received: {...}
💰 Amount from GoHighLevel: 25.5 JOD
👤 Customer Info: {...}
📋 Step 2: Simulating successful payment...
✅ Mock token received: {...}
📋 Step 3: Simulating backend verification...
🔗 GHL would call: POST /payment/query
✅ Backend would respond: { success: true }
🎉 User would be redirected to success page
```

## 📋 **Complete Payment Flow (Per GHL Documentation)**

### **1. User Initiates Payment**
- User clicks "Pay Now" in GHL funnel/form

### **2. GHL Loads Your Iframe**
- GHL loads: `https://dashboard.mediasolution.io/tap`

### **3. Your Iframe Sends Ready Event**
```javascript
{
  type: 'custom_provider_ready',
  loaded: true,
  addCardOnFileSupported: true
}
```

### **4. GHL Sends Payment Data**
```javascript
{
  type: 'payment_initiate_props',
  publishableKey: 'pk_live_xxxxx',
  amount: 25.50,
  currency: 'JOD',
  mode: 'payment',
  contact: { id: 'cus_123', name: 'John Doe', email: 'john@example.com' },
  orderId: 'order_456',
  transactionId: 'txn_789',
  locationId: 'loc_101'
}
```

### **5. User Enters Card Info**
- User fills card details and clicks "Pay Now"

### **6. Payment Success Response**
```javascript
{
  type: 'custom_element_success_response',
  chargeId: 'tok_xxxxx'
}
```

### **7. GHL Calls Backend Verification**
```bash
POST /payment/query
{
  "type": "verify",
  "transactionId": "txn_789",
  "apiKey": "api_key_here",
  "chargeId": "tok_xxxxx",
  "subscriptionId": "sub_123"
}
```

### **8. Backend Response**
```javascript
{
  "success": true
}
```

### **9. GHL Redirects to Success Page**
- User sees success page

## 🔍 **Debugging Steps**

### **Check Console Messages**

Look for these key messages:

✅ **Good Signs:**
```
✅ GHL Payment data received: {...}
💰 Amount from GoHighLevel: 25.5 JOD
👤 Customer Info: {...}
```

❌ **Problem Signs:**
```
⚠️ WARNING: No payment data received from GHL after 5 seconds
🔍 This could mean:
  1. GHL integration not configured properly
  2. Payment URL not set correctly in GHL
  3. API keys not configured in GHL
  4. Integration not activated in GHL
  5. Testing in wrong GHL environment
```

### **Check Message Origins**

In your console, look for message origins:

✅ **Expected:**
```
🔍 Received message from origin: https://app.mediasolution.io
Data: { type: 'payment_initiate_props', amount: 25.50, ... }
```

❌ **Not GHL Data:**
```
🔍 Received message from origin: https://dashboard.mediasolution.io
🔍 Received message from origin: https://sdk.tap.company
```

## 🎯 **Next Steps**

1. **Fix GHL Integration Configuration**
   - Verify payment URL
   - Check API keys
   - Activate integration

2. **Test in Real GHL Environment**
   - Create test funnel
   - Test payment flow
   - Verify data reception

3. **Monitor Console Output**
   - Look for GHL payment data
   - Check for success responses
   - Verify backend verification

## 🚀 **Expected Result**

When working correctly, you should see:

```
📤 Sending ready event to GHL: {type: 'custom_provider_ready', loaded: true, addCardOnFileSupported: true}
✅ Payment iframe is ready and listening for GHL messages

// Then automatically (without running test functions):
🔍 Received message from origin: https://app.mediasolution.io
✅ GHL Payment data received: {type: 'payment_initiate_props', amount: 25.50, ...}
💰 Amount from GoHighLevel: 25.5 JOD
👤 Customer Info: {id: 'cus_123', name: 'John Doe', ...}
```

The integration is working perfectly - you just need to fix the GHL configuration to send the payment data!

