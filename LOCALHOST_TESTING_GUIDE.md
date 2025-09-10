# Localhost Testing Guide - GoHighLevel + Tap Integration

## 🚀 Quick Start

Your Laravel server is now running at: **http://localhost:8000**

## 📋 Test Cards for Tap Payments

### ✅ **Successful Test Cards**

| Card Number | Expiry | CVV | Description |
|-------------|--------|-----|-------------|
| `4242424242424242` | `112/25` | `123` | Visa - Success |
| `5555555555554444` | `12/25` | `123` | Mastercard - Success |
| `378282246310005` | `12/25` | `123` | American Express - Success |
| `6011111111111117` | `12/25` | `123` | Discover - Success |

### ❌ **Declined Test Cards**

| Card Number | Expiry | CVV | Description |
|-------------|--------|-----|-------------|
| `4000000000000002` | `12/25` | `123` | Card Declined |
| `4000000000009995` | `12/25` | `123` | Insufficient Funds |
| `4000000000000069` | `12/25` | `123` | Expired Card |
| `4000000000000119` | `12/25` | `123` | Processing Error |

### 🔄 **3D Secure Test Cards**

| Card Number | Expiry | CVV | Description |
|-------------|--------|-----|-------------|
| `4000000000003220` | `12/25` | `123` | 3D Secure Authentication Required |
| `4000000000003063` | `12/25` | `123` | 3D Secure Authentication Failed |

## 🧪 Step-by-Step Testing

### Step 1: Test the Payment Form

1. **Open your browser** and go to: `http://localhost:8000/tap`
2. **You should see** a beautiful payment form with Tap Card SDK
3. **Try entering** one of the successful test cards above
4. **Click "Pay Now"** to test tokenization

### Step 2: Test Payment Query Endpoint

1. **Open a new tab** and go to: `http://localhost:8000/test-payment-query`
2. **You should see** a JSON response like:
```json
{
  "success": false,
  "message": "Location not found"
}
```
This is expected since we're using test data.

### Step 3: Test with Real GoHighLevel Integration

#### Option A: Using ngrok (Recommended)

1. **Install ngrok** if you don't have it:
```bash
# Download from https://ngrok.com/download
# Or install via Homebrew:
brew install ngrok
```

2. **Start ngrok**:
```bash
ngrok http 8000
```

3. **Copy the HTTPS URL** (e.g., `https://abc123.ngrok.io`)

4. **Update your GoHighLevel app settings** with:
   - Redirect URL: `https://abc123.ngrok.io/connect`
   - Webhook URL: `https://abc123.ngrok.io/webhook`
   - Query URL: `https://abc123.ngrok.io/payment/query`
   - Payments URL: `https://abc123.ngrok.io/tap`

#### Option B: Using Laravel Valet (if you have it)

1. **Link your project**:
```bash
valet link highlevel
```

2. **Use the generated URL**: `https://highlevel.test`

### Step 4: Test OAuth Flow

1. **Go to your GoHighLevel marketplace app**
2. **Click "Install"** on your app
3. **You should be redirected** to your localhost app
4. **Check the logs** to see the OAuth flow:
```bash
tail -f storage/logs/laravel.log
```

### Step 5: Test Payment Processing

1. **After OAuth completes**, GoHighLevel will call your query endpoint
2. **Check the logs** to see payment requests
3. **Test different payment types** by calling the query endpoint directly

## 🔧 Configuration for Testing

### Update Your Tap Keys

1. **Get test keys** from Tap Dashboard
2. **Update the tap.blade.php** with your test publishable key:
```javascript
publicKey: 'pk_test_YOUR_TEST_KEY_HERE'
```

### Test API Keys

You can test with these sample API keys (replace with your real test keys):

**Test Mode:**
- API Key: `sk_test_...`
- Publishable Key: `pk_test_...`

**Live Mode:**
- API Key: `sk_live_...`
- Publishable Key: `pk_live_...`

## 🐛 Debugging

### Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

### Check Database
```bash
php artisan tinker
```
```php
// Check if users are being created
User::all();

// Check a specific user's API keys
$user = User::first();
$user->lead_test_api_key; // Should show encrypted value
```

### Test Individual Components

1. **Test OAuth callback**:
```bash
curl "http://localhost:8000/connect?code=test_code"
```

2. **Test payment query**:
```bash
curl -X POST "http://localhost:8000/payment/query" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "verify",
    "locationId": "test_location",
    "apiKey": "test_key",
    "transactionId": "test_transaction"
  }'
```

3. **Test webhook**:
```bash
curl -X POST "http://localhost:8000/webhook" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "payment.captured",
    "locationId": "test_location",
    "apiKey": "test_key",
    "chargeId": "test_charge"
  }'
```

## 📱 Mobile Testing

1. **Find your computer's IP address**:
```bash
ifconfig | grep "inet " | grep -v 127.0.0.1
```

2. **Access from mobile**: `http://YOUR_IP:8000/tap`

3. **Test on mobile device** with the same test cards

## 🔍 Common Issues & Solutions

### Issue: "Tap SDK not loading"
**Solution**: Check browser console for errors, verify publishable key

### Issue: "OAuth flow fails"
**Solution**: Check redirect URL in GoHighLevel matches your localhost URL

### Issue: "Payment query returns 404"
**Solution**: Ensure the queryUrl is set to `/payment/query` in GoHighLevel

### Issue: "Database errors"
**Solution**: Run migrations: `php artisan migrate`

## 📊 Expected Results

### Successful Payment Flow:
1. User enters valid test card
2. Tap SDK tokenizes the card
3. Token is displayed in the result section
4. No errors in browser console
5. Laravel logs show successful processing

### Successful OAuth Flow:
1. User clicks install in GoHighLevel
2. Redirected to your app
3. User record created in database
4. Payment provider registered with GoHighLevel
5. Success message displayed

## 🎯 Next Steps

1. **Test with real Tap test keys**
2. **Test the complete GoHighLevel integration**
3. **Test webhook events**
4. **Test different payment scenarios**
5. **Deploy to production when ready**

## 📞 Support

If you encounter issues:
1. Check the Laravel logs first
2. Verify your Tap API keys
3. Check GoHighLevel app configuration
4. Test individual components separately

Happy testing! 🚀
