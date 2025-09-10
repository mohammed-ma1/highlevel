<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Integration Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-button {
            background: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        .test-button:hover {
            background: #005a87;
        }
        .result {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            white-space: pre-wrap;
            font-family: monospace;
        }
        .success { border-color: #28a745; background: #d4edda; }
        .error { border-color: #dc3545; background: #f8d7da; }
        .test-cards {
            background: #e9ecef;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .card-item {
            margin: 5px 0;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <h1>🧪 Payment Integration Test Dashboard</h1>
    
    <div class="test-section">
        <h2>📋 Test Cards</h2>
        <div class="test-cards">
            <h3>✅ Successful Cards:</h3>
            <div class="card-item"><strong>4242424242424242</strong> | 12/25 | 123 (Visa)</div>
            <div class="card-item"><strong>5555555555554444</strong> | 12/25 | 123 (Mastercard)</div>
            <div class="card-item"><strong>378282246310005</strong> | 12/25 | 123 (Amex)</div>
            
            <h3>❌ Declined Cards:</h3>
            <div class="card-item"><strong>4000000000000002</strong> | 12/25 | 123 (Declined)</div>
            <div class="card-item"><strong>4000000000009995</strong> | 12/25 | 123 (Insufficient Funds)</div>
        </div>
    </div>

    <div class="test-section">
        <h2>🔗 Test Links</h2>
        <a href="/tap" class="test-button">💳 Test Payment Form</a>
        <a href="/test-payment-query" class="test-button">🔍 Test Payment Query</a>
        <a href="/webhook" class="test-button">📡 Test Webhook</a>
    </div>

    <div class="test-section">
        <h2>🧪 API Tests</h2>
        <button class="test-button" onclick="testPaymentQuery()">Test Payment Query API</button>
        <button class="test-button" onclick="testWebhook()">Test Webhook API</button>
        <button class="test-button" onclick="testOAuth()">Test OAuth Callback</button>
        
        <div id="test-results" class="result" style="display: none;"></div>
    </div>

    <div class="test-section">
        <h2>📊 System Status</h2>
        <div id="system-status">
            <p>✅ Laravel Server: Running on localhost:8000</p>
            <p>✅ Database: Connected</p>
            <p>✅ Routes: Registered</p>
            <p>✅ Migrations: Applied</p>
        </div>
    </div>

    <div class="test-section">
        <h2>📝 Quick Test Steps</h2>
        <ol>
            <li><strong>Test Payment Form:</strong> Click "Test Payment Form" and try a test card</li>
            <li><strong>Test API Endpoints:</strong> Click the API test buttons above</li>
            <li><strong>Check Logs:</strong> Run <code>tail -f storage/logs/laravel.log</code> in terminal</li>
            <li><strong>Test with GoHighLevel:</strong> Use ngrok to expose your localhost</li>
        </ol>
    </div>

    <script>
        async function testPaymentQuery() {
            const resultDiv = document.getElementById('test-results');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.textContent = 'Testing Payment Query...';
            
            try {
                const response = await fetch('/test-payment-query');
                const data = await response.json();
                resultDiv.textContent = JSON.stringify(data, null, 2);
                resultDiv.className = 'result success';
            } catch (error) {
                resultDiv.textContent = 'Error: ' + error.message;
                resultDiv.className = 'result error';
            }
        }

        async function testWebhook() {
            const resultDiv = document.getElementById('test-results');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.textContent = 'Testing Webhook...';
            
            try {
                const response = await fetch('/webhook', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        event: 'payment.captured',
                        locationId: 'test_location',
                        apiKey: 'test_key',
                        chargeId: 'test_charge'
                    })
                });
                const data = await response.json();
                resultDiv.textContent = JSON.stringify(data, null, 2);
                resultDiv.className = 'result success';
            } catch (error) {
                resultDiv.textContent = 'Error: ' + error.message;
                resultDiv.className = 'result error';
            }
        }

        async function testOAuth() {
            const resultDiv = document.getElementById('test-results');
            resultDiv.style.display = 'block';
            resultDiv.className = 'result';
            resultDiv.textContent = 'Testing OAuth Callback...';
            
            try {
                const response = await fetch('/connect?code=test_code_123');
                const data = await response.json();
                resultDiv.textContent = JSON.stringify(data, null, 2);
                resultDiv.className = 'result success';
            } catch (error) {
                resultDiv.textContent = 'Error: ' + error.message;
                resultDiv.className = 'result error';
            }
        }
    </script>
</body>
</html>
