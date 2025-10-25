# Debug Implementation - GHL Payment Event Handling

## ✅ **What Should Happen When GHL Payment Event is Received**

When you receive this message:
```json
{
  "type": "payment_initiate_props",
  "publishableKey": "pk_test_xItqaSsJzl5g2K08fCwYbMvQ",
  "amount": 1,
  "currency": "usd",
  "mode": "payment",
  "productDetails": [{}],
  "name": "",
  "description": "",
  "image": "",
  "contact": {
    "id": "HxkOfigmSsweHM1Tg94J",
    "name": "Mohammad mousa aladawe",
    "email": "mohammed.aladawi98@gmail.com",
    "shippingAddress": {}
  },
  "orderId": "68fcdb3c3afe7e05edc58384",
  "invoiceId": "",
  "transactionId": "68fcdb3c3afe7e39e9c583a3",
  "locationId": "xAN9Y8iZDOugbNvKBKad",
  "language": "en-US"
}
```

## 🔧 **Expected Console Logs**

You should see these console logs in sequence:

### **1. Message Received**
```
📨 Received message from parent: {type: "payment_initiate_props", ...}
💰 GHL Payment event received: {type: "payment_initiate_props", ...}
```

### **2. Function Called**
```
🚀 Auto-creating charge and redirecting to Tap checkout...
📊 Payment data available: {type: "payment_initiate_props", ...}
🚀 Creating charge with data: {type: "payment_initiate_props", ...}
🔗 API endpoint: /api/charge/create-tap
```

### **3. API Request**
```
📤 Sending request to API: {
  url: "/api/charge/create-tap",
  method: "POST",
  body: {
    amount: 1,
    currency: "usd",
    customer_initiated: true,
    threeDSecure: true,
    save_card: false,
    description: "Payment for product",
    metadata: {
      udf1: "Order: 68fcdb3c3afe7e05edc58384",
      udf2: "Transaction: 68fcdb3c3afe7e39e9c583a3",
      udf3: "Location: xAN9Y8iZDOugbNvKBKad"
    },
    receipt: {email: false, sms: false},
    reference: {
      transaction: "68fcdb3c3afe7e39e9c583a3",
      order: "68fcdb3c3afe7e05edc58384"
    },
    customer: {
      first_name: "Mohammad",
      last_name: "mousa aladawe",
      email: "mohammed.aladawi98@gmail.com",
      phone: {country_code: 965, number: 790000000}
    },
    merchant: {id: "xAN9Y8iZDOugbNvKBKad"},
    post: {url: "http://localhost:8000/charge/webhook"},
    redirect: {url: "http://localhost:8000/payment/success?charge_id="}
  }
}
```

### **4. API Response**
```
📡 Response status: 200
📡 Response headers: {content-type: "application/json", ...}
📡 Response data: {success: true, charge: {...}, message: "Charge created successfully"}
✅ API call successful: true
```

### **5. Success Processing**
```
✅ Tap charge created successfully: {id: "chg_xxx", status: "INITIATED", ...}
🆔 Charge ID: chg_xxx
🔗 Transaction URL: https://checkout.tap.company/?mode=page&...
💾 Stored in sessionStorage: {
  tap_charge_id: "chg_xxx",
  ghl_transaction_id: "68fcdb3c3afe7e39e9c583a3",
  ghl_order_id: "68fcdb3c3afe7e05edc58384"
}
🔗 Redirecting to Tap checkout: https://checkout.tap.company/?mode=page&...
🚀 About to redirect...
```

## 🧪 **Testing Steps**

1. **Open browser console** to see the logs
2. **Load the charge page** - should show loading screen
3. **Send GHL payment event** - should trigger the API call
4. **Check console logs** - should see all the debug messages above
5. **Verify API call** - should see the request and response
6. **Check redirect** - should redirect to Tap checkout

## 🔍 **Troubleshooting**

### **If API is not called:**
- Check if `createChargeAndRedirect()` function is being called
- Look for any JavaScript errors in console
- Verify the message handler is working

### **If API call fails:**
- Check the request body in console logs
- Verify the API endpoint is accessible
- Check for CORS issues
- Look at Laravel server logs

### **If redirect doesn't work:**
- Check if `result.charge.transaction?.url` exists
- Verify the URL is valid
- Check for any JavaScript errors

## ✅ **Expected Result**

After receiving the GHL payment event, the page should:
1. Show "Processing Payment" loading screen
2. Make API call to `/api/charge/create-tap`
3. Receive successful response with Tap checkout URL
4. Redirect to Tap checkout page
5. User completes payment on Tap
6. Return to success page for verification

The implementation should now work with comprehensive debugging! 🎉
