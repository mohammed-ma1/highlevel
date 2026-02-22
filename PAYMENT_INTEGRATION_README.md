# GoHighLevel Payments Integration (Tap + UPayments)

This Laravel application provides custom payment integrations for GoHighLevel:

- **Tap** (existing integration, unchanged)
- **UPayments** (new integration, hosted checkout link / Non-Whitelabel)

The integration follows the [GoHighLevel Custom Payment Integration documentation](https://help.gohighlevel.com/support/solutions/articles/155000002620-how-to-build-a-custom-payments-integration-on-the-platform).

## Features

- ✅ OAuth integration with GoHighLevel
- ✅ Tap Card SDK integration for secure payments
- ✅ UPayments hosted checkout (Non-Whitelabel) integration
- ✅ Payment query endpoint for GoHighLevel
- ✅ Webhook handling for payment events
- ✅ Support for one-time payments and subscriptions
- ✅ Test and live mode configurations
- ✅ Encrypted storage of API keys

## Architecture

### Controllers

1. **ClientIntegrationController** - Handles OAuth flow and GoHighLevel integration
2. **PaymentQueryController** - Handles payment queries from GoHighLevel
3. **TapPaymentService** - Service class for Tap API interactions

### Key Endpoints

- `GET /connect` - OAuth callback from GoHighLevel
- `GET /uconnect` - OAuth callback for the UPayments marketplace app
- `POST /payment/query` - Payment processing endpoint for GoHighLevel
- `POST /api/upayment/query` - UPayments payment query endpoint for GoHighLevel
- `POST /webhook` - Webhook handler for payment events
- `GET /tap` - Payment form with Tap Card SDK
- `GET /ucharge` - Payment UI for UPayments hosted checkout
- `POST /provider/connect-or-disconnect` - Connect/disconnect payment provider
- `POST /uprovider/connect-or-disconnect` - Connect/disconnect UPayments provider
- `GET /upayment/redirect` - Return/cancel redirect page for UPayments hosted checkout
- `GET /api/upayment/status` - Server-side status check using `track_id`
- `POST /api/upayment/webhook` - UPayments `notificationUrl` webhook receiver

## Setup Instructions

### 1. Database Migration

Run the migrations to add the required fields:

```bash
php artisan migrate
```

This will add the following fields to the users table:
- `lead_live_api_key` - Tap live API key
- `lead_live_publishable_key` - Tap live publishable key
- `lead_test_api_key` - Tap test API key
- `lead_test_publishable_key` - Tap test publishable key

And for UPayments:
- `upayments_mode` - `test|live`
- `upayments_test_token` - UPayments sandbox token (e.g. `jtest123`)
- `upayments_live_merchant_id` - UPayments production Merchant ID
- `upayments_live_api_key` - UPayments production API Key (Bearer Token)
- `upayments_live_token` - (legacy) UPayments production token field (still supported; treated as API Key)

### 2. GoHighLevel Marketplace App Configuration

When creating your marketplace app in GoHighLevel, use these settings:

#### Required Scopes
```
payments/orders.readonly
payments/orders.write
payments/subscriptions.readonly
payments/transactions.readonly
payments/custom-provider.readonly
payments/custom-provider.write
products.readonly
products/prices.readonly
```

#### URLs
- **Redirect URL**: `https://yourdomain.com/connect`
- **Redirect URL (UPayments app)**: `https://yourdomain.com/uconnect`
- **Webhook URL**: `https://yourdomain.com/webhook`
- **Query URL**: `https://yourdomain.com/payment/query`
- **Payments URL**: `https://yourdomain.com/tap`

For UPayments provider registration payload (these URLs are sent to GHL during connect):
- **UPayments Landing URL**: `https://yourdomain.com/Ulanding`
- **UPayments Payments URL**: `https://yourdomain.com/ucharge`
- **UPayments Query URL**: `https://yourdomain.com/api/upayment/query`

#### Payment Provider Type
Select the appropriate types:
- ✅ OneTime (for one-time payments)
- ✅ Recurring (for subscriptions)
- ✅ Off Session (for saved payment methods)

### 3. Tap Payment Configuration

1. Get your Tap API keys from the Tap Dashboard
2. Configure test and live keys in your GoHighLevel app
3. The keys will be securely stored in the database

### 4. UPayments Configuration

1. Create/install the **UPayments** marketplace app (separate OAuth client) and complete OAuth via `GET /uconnect`
2. Open the setup UI at `GET /Ulanding` from the GHL integration flow
3. Enter the UPayments **Test Token** (Sandbox) and/or **Live Merchant ID + API Key** (Production), select the mode, and click **Connect Provider**

Production endpoints:
- Production API base URL: `https://apiv2api.upayments.com/api/v1/`
- Example: `https://sandboxapi.upayments.com/api/v1/charge` → `https://apiv2api.upayments.com/api/v1/charge`

UPayments API reference:
- Create charge: [UPayments “Make charge”](https://developers.upayments.com/reference/addcharge)
- Get status: [UPayments “Get Payment Status”](https://developers.upayments.com/reference/checkpaymentstatus)

## Payment Flow

### 1. App Installation
1. User installs your app in GoHighLevel
2. OAuth flow completes and user is created
3. Payment provider is registered with GoHighLevel

### 2. Payment Processing
1. GoHighLevel sends payment requests to `/payment/query`
2. The endpoint processes different payment types:
   - `verify` - Verify a transaction
   - `refund` - Process a refund
   - `charge` - Create a charge
   - `create_subscription` - Create a subscription
   - `update_subscription` - Update a subscription
   - `cancel_subscription` - Cancel a subscription
   - `list_payment_methods` - List customer payment methods

### 3. Frontend Payment
1. User is redirected to `/tap` for payment
2. Tap Card SDK renders the payment form
3. User enters card details and submits
4. Tap tokenizes the card and returns a token
5. Token is sent to your backend for processing

## API Integration

### Payment Query Endpoint

**URL**: `POST /payment/query`

**Required Parameters**:
- `type` - Payment operation type
- `locationId` - GoHighLevel location ID
- `apiKey` - Payment provider API key

**Example Request**:
```json
{
  "type": "verify",
  "locationId": "location_123",
  "apiKey": "api_key_123",
  "transactionId": "transaction_123"
}
```

**Example Response**:
```json
{
  "success": true,
  "verified": true,
  "transactionId": "transaction_123",
  "status": "completed"
}
```

### Webhook Events

The webhook endpoint handles these events:
- `subscription.charged`
- `subscription.trialing`
- `subscription.active`
- `subscription.updated`
- `payment.captured`

### UPayments Payment Query Endpoint (Verify)

**URL**: `POST /api/upayment/query`

**Behavior**:
- Uses UPayments `track_id` as the `chargeId` (sent back to GHL via `GET /upayment/redirect`)
- Verifies via UPayments status API (`GET .../get-payment-status/{track_id}`) and maps to GHL responses
- Always returns HTTP 200

**Example Request**:
```json
{
  "type": "verify",
  "locationId": "location_123",
  "apiKey": "jtest123",
  "chargeId": "019988a5066a999800a57eb83d309bafv2"
}
```

## Testing

### Test Payment Query
Visit `/test-payment-query` to test the payment query endpoint.

### Test Payment Form
Visit `/tap` to test the payment form with Tap Card SDK.

## Security

- All API keys are encrypted in the database
- OAuth tokens are encrypted
- Webhook endpoints validate location IDs
- All API calls use HTTPS

## Troubleshooting

### Common Issues

1. **OAuth Flow Fails**
   - Check your redirect URL in GoHighLevel
   - Verify client ID and secret are correct

2. **Payment Query Fails**
   - Ensure the queryUrl is correctly set to `/payment/query`
   - Check that API keys are properly configured

3. **Tap SDK Not Loading**
   - Verify your publishable key is correct
   - Check browser console for errors

4. **UPayments Verify Fails**
   - Ensure the provider connect used `queryUrl = /api/upayment/query`
   - Ensure `upayments_mode` matches the token you provided
   - Ensure `GET /upayment/redirect` is reachable (UPayments must redirect back)

### Logs

Check Laravel logs for detailed error information:
```bash
tail -f storage/logs/laravel.log
```

## Development

### Adding New Payment Types

1. Add the new type to the switch statement in `PaymentQueryController::handleQuery()`
2. Create a new handler method
3. Implement the logic using `TapPaymentService`

### Customizing the Payment Form

Edit `resources/views/tap.blade.php` to customize:
- Styling and layout
- Supported card brands
- Customer information fields
- Error handling

## Support

For issues with:
- GoHighLevel integration: Check the [GoHighLevel documentation](https://help.gohighlevel.com/support/solutions/articles/155000002620-how-to-build-a-custom-payments-integration-on-the-platform)
- Tap payments: Check the [Tap documentation](https://developers.tap.company/docs/card-sdk-web-v2)
- This integration: Check the Laravel logs and code comments
