<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payment Processing - {{ config('app.name', 'Laravel') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

        .payment-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
            position: relative;
        }

        .payment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .payment-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .payment-header p {
            font-size: 16px;
            opacity: 0.9;
            font-weight: 400;
        }

        .payment-content {
            padding: 40px 30px;
            text-align: center;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
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

        .status-message {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
        }

        .status-details {
            font-size: 14px;
            color: #666;
            margin-bottom: 30px;
        }

        .success-icon {
            color: #10b981;
            font-size: 48px;
            margin-bottom: 20px;
        }

        .error-icon {
            color: #ef4444;
            font-size: 48px;
            margin-bottom: 20px;
        }

        .retry-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .retry-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .retry-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            font-size: 12px;
            color: #666;
            text-align: left;
        }

        .debug-info h4 {
            margin-bottom: 10px;
            color: #333;
        }

        .debug-info pre {
            background: #fff;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1><i class="fas fa-credit-card"></i> Payment Processing</h1>
            <p>Verifying your payment status...</p>
        </div>
        
        <div class="payment-content">
            <div id="loading-spinner" class="loading-spinner"></div>
            <div id="status-message" class="status-message">Processing your payment...</div>
            <div id="status-details" class="status-details">Please wait while we verify your transaction.</div>
            
            <button id="retry-button" class="retry-button" style="display: none;" onclick="retryVerification()">
                <i class="fas fa-redo"></i> Retry Verification
            </button>
            
            <div id="debug-info" class="debug-info" style="display: none;">
                <h4>Debug Information</h4>
                <pre id="debug-content"></pre>
            </div>
        </div>
    </div>

    <script>
        let chargeId = null;
        let transactionId = null;
        let orderId = null;
        let verificationAttempts = 0;
        const maxAttempts = 10;
        const retryDelay = 2000; // 2 seconds

        // Get charge ID from URL parameters
        function getChargeIdFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('charge_id');
        }

        // Get stored data from session storage
        function getStoredData() {
            return {
                chargeId: sessionStorage.getItem('tap_charge_id'),
                transactionId: sessionStorage.getItem('ghl_transaction_id'),
                orderId: sessionStorage.getItem('ghl_order_id')
            };
        }

        // Update UI elements
        function updateStatus(message, details = '', showSpinner = true, showRetry = false) {
            document.getElementById('status-message').textContent = message;
            document.getElementById('status-details').textContent = details;
            document.getElementById('loading-spinner').style.display = showSpinner ? 'block' : 'none';
            document.getElementById('retry-button').style.display = showRetry ? 'block' : 'none';
        }

        // Show success state
        function showSuccess(chargeId) {
            document.getElementById('loading-spinner').style.display = 'none';
            document.getElementById('status-message').innerHTML = '<i class="fas fa-check-circle success-icon"></i><br>Payment Successful!';
            document.getElementById('status-details').textContent = 'Your payment has been processed successfully.';
            
            // Send success response to GHL parent window
            sendResponseToGHL('success', { chargeId: chargeId });
        }

        // Show error state
        function showError(message) {
            document.getElementById('loading-spinner').style.display = 'none';
            document.getElementById('status-message').innerHTML = '<i class="fas fa-times-circle error-icon"></i><br>Payment Failed';
            document.getElementById('status-details').textContent = message;
            document.getElementById('retry-button').style.display = 'block';
            
            // Send error response to GHL parent window
            sendResponseToGHL('error', { description: message });
        }

        // Show canceled state
        function showCanceled() {
            document.getElementById('loading-spinner').style.display = 'none';
            document.getElementById('status-message').innerHTML = '<i class="fas fa-ban error-icon"></i><br>Payment Canceled';
            document.getElementById('status-details').textContent = 'You canceled the payment process.';
            
            // Send close response to GHL parent window
            sendResponseToGHL('close');
        }

        // Send response to GHL parent window
        function sendResponseToGHL(type, data = {}) {
            let response;
            
            switch (type) {
                case 'success':
                    response = {
                        type: 'custom_element_success_response',
                        chargeId: data.chargeId
                    };
                    break;
                case 'error':
                    response = {
                        type: 'custom_element_error_response',
                        error: {
                            description: data.description
                        }
                    };
                    break;
                case 'close':
                    response = {
                        type: 'custom_element_close_response'
                    };
                    break;
            }
            
            console.log('📤 Sending response to GHL:', response);
            
            try {
                if (window.parent && window.parent !== window) {
                    window.parent.postMessage(JSON.stringify(response), '*');
                }
                
                if (window.top && window.top !== window && window.top !== window.parent) {
                    window.top.postMessage(JSON.stringify(response), '*');
                }
            } catch (error) {
                console.warn('⚠️ Could not send response to parent:', error.message);
            }
        }

        // Verify payment status with backend
        async function verifyPaymentStatus() {
            if (!chargeId) {
                console.error('❌ No charge ID available for verification');
                showError('No charge ID found. Please try again.');
                return;
            }

            console.log('🔍 Verifying payment status for charge:', chargeId);
            updateStatus('Verifying payment...', `Attempt ${verificationAttempts + 1} of ${maxAttempts}`, true, false);

            try {
                const response = await fetch('/api/charge/verify', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    },
                    body: JSON.stringify({
                        chargeId: chargeId,
                        transactionId: transactionId,
                        orderId: orderId
                    })
                });

                const result = await response.json();
                console.log('📡 Verification response:', result);

                if (response.ok && result.success) {
                    console.log('✅ Payment verified successfully');
                    showSuccess(chargeId);
                    return;
                } else if (result.failed) {
                    console.log('❌ Payment failed');
                    showError(result.message || 'Payment verification failed');
                    return;
                } else {
                    // Still pending, retry if we haven't exceeded max attempts
                    verificationAttempts++;
                    if (verificationAttempts < maxAttempts) {
                        console.log(`⏳ Payment still pending, retrying in ${retryDelay}ms... (${verificationAttempts}/${maxAttempts})`);
                        setTimeout(verifyPaymentStatus, retryDelay);
                    } else {
                        console.log('⏰ Max verification attempts reached');
                        showError('Payment verification timed out. Please contact support.');
                    }
                }
            } catch (error) {
                console.error('❌ Error verifying payment:', error);
                verificationAttempts++;
                
                if (verificationAttempts < maxAttempts) {
                    console.log(`🔄 Retrying verification in ${retryDelay}ms... (${verificationAttempts}/${maxAttempts})`);
                    setTimeout(verifyPaymentStatus, retryDelay);
                } else {
                    showError('Unable to verify payment status. Please contact support.');
                }
            }
        }

        // Retry verification
        function retryVerification() {
            verificationAttempts = 0;
            document.getElementById('retry-button').style.display = 'none';
            verifyPaymentStatus();
        }

        // Show debug information
        function showDebugInfo() {
            const debugContent = document.getElementById('debug-content');
            debugContent.textContent = JSON.stringify({
                chargeId: chargeId,
                transactionId: transactionId,
                orderId: orderId,
                verificationAttempts: verificationAttempts,
                urlParams: Object.fromEntries(new URLSearchParams(window.location.search)),
                sessionStorage: {
                    tap_charge_id: sessionStorage.getItem('tap_charge_id'),
                    ghl_transaction_id: sessionStorage.getItem('ghl_transaction_id'),
                    ghl_order_id: sessionStorage.getItem('ghl_order_id')
                }
            }, null, 2);
            document.getElementById('debug-info').style.display = 'block';
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Payment Success Page Loaded');
            
            // Get charge ID from URL or session storage
            const urlChargeId = getChargeIdFromUrl();
            const storedData = getStoredData();
            
            chargeId = urlChargeId || storedData.chargeId;
            transactionId = storedData.transactionId;
            orderId = storedData.orderId;
            
            console.log('🔍 Payment data:', {
                chargeId: chargeId,
                transactionId: transactionId,
                orderId: orderId,
                urlChargeId: urlChargeId,
                storedData: storedData
            });
            
            if (!chargeId) {
                console.error('❌ No charge ID found');
                showError('No payment information found. Please try again.');
                showDebugInfo();
                return;
            }
            
            // Check if user canceled (you can add URL parameter detection here)
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('canceled') === 'true') {
                showCanceled();
                return;
            }
            
            // Start verification process
            console.log('🔍 Starting payment verification...');
            verifyPaymentStatus();
            
            // Show debug info in development
            if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
                showDebugInfo();
            }
        });
    </script>
</body>
</html>
