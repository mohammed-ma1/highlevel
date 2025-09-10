# GoHighLevel + Tap Payment Integration

This Laravel application provides a custom payment integration for GoHighLevel using Tap Payment Gateway. The integration follows the [GoHighLevel Custom Payment Integration documentation](https://help.gohighlevel.com/support/solutions/articles/155000002620-how-to-build-a-custom-payments-integration-on-the-platform).

## Features

- ✅ OAuth integration with GoHighLevel
- ✅ Tap Card SDK integration for secure payments
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
- `POST /payment/query` - Payment processing endpoint for GoHighLevel
- `POST /webhook` - Webhook handler for payment events
- `GET /tap` - Payment form with Tap Card SDK
- `POST /provider/connect-or-disconnect` - Connect/disconnect payment provider

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
- **Webhook URL**: `https://yourdomain.com/webhook`
- **Query URL**: `https://yourdomain.com/payment/query`
- **Payments URL**: `https://yourdomain.com/tap`

#### Payment Provider Type
Select the appropriate types:
- ✅ OneTime (for one-time payments)
- ✅ Recurring (for subscriptions)
- ✅ Off Session (for saved payment methods)

### 3. Tap Payment Configuration

1. Get your Tap API keys from the Tap Dashboard
2. Configure test and live keys in your GoHighLevel app
3. The keys will be securely stored in the database

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
