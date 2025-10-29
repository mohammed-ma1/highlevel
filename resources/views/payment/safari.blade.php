<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Safari Payment - {{ config('app.name', 'Laravel') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        .safari-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
            text-align: center;
            padding: 40px 30px;
        }

        .safari-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .safari-title {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 12px;
        }

        .safari-message {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.5;
        }

        .payment-info {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #374151;
        }

        .info-value {
            color: #6b7280;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
        }

        .btn {
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 16px;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .error-message {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        @media (max-width: 480px) {
            .safari-container {
                margin: 10px;
                padding: 30px 20px;
            }
            
            .safari-title {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="safari-container">
        <div class="safari-icon">🍎</div>
        <h1 class="safari-title">Safari Payment</h1>
        <p class="safari-message">Due to Safari's security restrictions, we need to process your payment in a secure full-page environment.</p>
        
        <div class="payment-info" id="payment-info" style="display: none;">
            <div class="info-item">
                <span class="info-label">Amount:</span>
                <span class="info-value" id="amount-display">-</span>
            </div>
            <div class="info-item">
                <span class="info-label">Currency:</span>
                <span class="info-value" id="currency-display">-</span>
            </div>
            <div class="info-item">
                <span class="info-label">Order ID:</span>
                <span class="info-value" id="order-display">-</span>
            </div>
        </div>

        <div class="loading-spinner" id="loading-spinner"></div>
        <div id="error-message" class="error-message" style="display: none;"></div>
        
        <button id="proceed-btn" class="btn btn-primary" style="display: none;">
            Proceed to Secure Payment
        </button>
    </div>

    <script>
        // Parse URL parameters
        function getUrlParams() {
            const params = new URLSearchParams(window.location.search);
            return {
                amount: params.get('amount'),
                currency: params.get('currency'),
                orderId: params.get('orderId'),
                transactionId: params.get('transactionId'),
                locationId: params.get('locationId'),
                customer: params.get('customer')
            };
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🍎 Safari payment page loaded');
            
            const params = getUrlParams();
            console.log('📋 URL Parameters:', params);
            
            // Update payment info display
            if (params.amount) {
                document.getElementById('amount-display').textContent = params.amount + ' ' + (params.currency || 'JOD');
                document.getElementById('currency-display').textContent = params.currency || 'JOD';
                document.getElementById('order-display').textContent = params.orderId || 'N/A';
                document.getElementById('payment-info').style.display = 'block';
            }
            
            // Hide loading spinner and show proceed button
            setTimeout(() => {
                document.getElementById('loading-spinner').style.display = 'none';
                document.getElementById('proceed-btn').style.display = 'inline-block';
            }, 2000);
            
            // Handle proceed button click
            document.getElementById('proceed-btn').addEventListener('click', function() {
                console.log('🍎 Proceeding to secure payment...');
                
                // Create the redirect URL
                const redirectUrl = window.location.origin + '/charge/safari-redirect?' + 
                    'amount=' + encodeURIComponent(params.amount || '') +
                    '&currency=' + encodeURIComponent(params.currency || 'JOD') +
                    '&orderId=' + encodeURIComponent(params.orderId || '') +
                    '&transactionId=' + encodeURIComponent(params.transactionId || '') +
                    '&locationId=' + encodeURIComponent(params.locationId || '') +
                    '&customer=' + encodeURIComponent(params.customer || '');
                
                console.log('🍎 Redirecting to:', redirectUrl);
                
                // Redirect to the payment processing page
                window.location.href = redirectUrl;
            });
        });
    </script>
</body>
</html>
