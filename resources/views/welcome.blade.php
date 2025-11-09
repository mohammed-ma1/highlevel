<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        <!-- Styles -->
        <style>
            /* Modern Professional Design */
            * {
                box-sizing: border-box;
            }

            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                margin: 0;
                padding: 0;
                color: #1a202c;
                line-height: 1.6;
            }

            .container {
                max-width: 900px;
                margin: 0 auto;
                padding: 2rem 1rem;
            }

            .card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
                overflow: hidden;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .card-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 2rem;
                text-align: center;
            }

            .card-header h1 {
                font-size: 1.875rem;
                font-weight: 700;
                margin: 0 0 0.5rem 0;
                letter-spacing: -0.025em;
            }

            .card-header p {
                font-size: 1rem;
                opacity: 0.9;
                margin: 0;
                font-weight: 400;
            }

            .card-body {
                padding: 2rem;
            }

            .section {
                margin-bottom: 2.5rem;
            }

            .section-title {
                font-size: 1.125rem;
                font-weight: 600;
                color: #2d3748;
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .section-title::before {
                content: '';
                width: 4px;
                height: 20px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 2px;
            }

            .form-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 1.5rem;
            }

            .form-group {
                display: flex;
                flex-direction: column;
            }

            .form-label {
                font-size: 0.875rem;
                font-weight: 500;
                color: #4a5568;
                margin-bottom: 0.5rem;
            }

            .form-input {
                padding: 0.875rem 1rem;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.875rem;
                transition: all 0.2s ease;
                background: #fafafa;
            }

            .form-input:focus {
                outline: none;
                border-color: #667eea;
                background: white;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }

            .form-input::placeholder {
                color: #a0aec0;
            }

            .mode-selector {
                display: flex;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            .mode-option {
                flex: 1;
                position: relative;
            }

            .mode-option input[type="radio"] {
                position: absolute;
                opacity: 0;
                pointer-events: none;
            }

            .mode-option label {
                display: block;
                padding: 1rem;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                text-align: center;
                cursor: pointer;
                transition: all 0.2s ease;
                background: #fafafa;
            }

            .mode-option input[type="radio"]:checked + label {
                border-color: #667eea;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }

            .mode-option label:hover {
                border-color: #cbd5e0;
                background: #f7fafc;
            }

            .mode-option input[type="radio"]:checked + label:hover {
                background: linear-gradient(135deg, #5a67d8 0%, #6b46c1 100%);
            }

            .action-buttons {
                display: flex;
                gap: 1rem;
                margin-top: 2rem;
                flex-wrap: wrap;
            }

            .btn {
                padding: 0.875rem 2rem;
                border-radius: 8px;
                font-size: 0.875rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s ease;
                border: none;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
                min-width: 120px;
            }

            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
            }

            .btn-secondary {
                background: white;
                color: #4a5568;
                border: 2px solid #e2e8f0;
            }

            .btn-secondary:hover {
                border-color: #cbd5e0;
                background: #f7fafc;
                transform: translateY(-1px);
            }

            .btn-danger {
                background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
                color: white;
                box-shadow: 0 4px 12px rgba(245, 101, 101, 0.3);
            }

            .btn-danger:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(245, 101, 101, 0.4);
            }

            .response-box {
                margin-top: 1.5rem;
                padding: 1rem;
                border-radius: 8px;
                font-size: 0.875rem;
                border-left: 4px solid;
            }

            .response-success {
                background: #f0fff4;
                border-color: #48bb78;
                color: #22543d;
            }

            .response-error {
                background: #fed7d7;
                border-color: #f56565;
                color: #742a2a;
            }

            .response-info {
                background: #ebf8ff;
                border-color: #4299e1;
                color: #2a4365;
            }

            .status-indicator {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-size: 0.75rem;
                font-weight: 500;
                margin-bottom: 1rem;
            }

            .status-connected {
                background: #f0fff4;
                color: #22543d;
                border: 1px solid #9ae6b4;
            }

            .status-disconnected {
                background: #fed7d7;
                color: #742a2a;
                border: 1px solid #feb2b2;
            }

            .help-text {
                font-size: 0.75rem;
                color: #718096;
                margin-top: 0.5rem;
                line-height: 1.4;
            }

            .divider {
                height: 1px;
                background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
                margin: 2rem 0;
            }

            @media (max-width: 768px) {
                .container {
                    padding: 1rem;
                }
                
                .card-body {
                    padding: 1.5rem;
                }
                
                .form-grid {
                    grid-template-columns: 1fr;
                }
                
                .action-buttons {
                    flex-direction: column;
                }
                
                .mode-selector {
                    flex-direction: column;
                }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1>🔗 Tap Payment Integration</h1>
                    <p>Connect your payment provider</p>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('provider.connect_or_disconnect') }}" id="paymentForm">
                        @csrf
                        <input type="hidden" id="information" name="information" value="{{ old('information', request()->input('information')) }}">

                        @if (session('success'))
                            {{-- Success Message --}}
                            <div class="section">
                                <div class="response-box response-success" style="text-align: center; padding: 2rem;">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                                    <h2 style="font-size: 1.5rem; font-weight: 600; color: #22543d; margin-bottom: 1rem;">
                                        Connection Successful!
                                    </h2>
                                    <p style="font-size: 1rem; color: #2d5016; margin-bottom: 1.5rem;">
                                        {{ session('message') ?? 'Provider config created/updated successfully' }}
                                    </p>
                                    @if (session('locationId'))
                                        <p style="font-size: 0.875rem; color: #4a5568; margin-bottom: 2rem;">
                                            Location ID: <strong>{{ session('locationId') }}</strong>
                                        </p>
                                    @endif
                                    <div style="background: #f0fff4; border: 2px solid #48bb78; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem;">
                                        <p style="font-size: 1.125rem; font-weight: 600; color: #22543d; margin-bottom: 0.5rem;">
                                            🎉 All done!
                                        </p>
                                        <p style="font-size: 1rem; color: #2d5016; margin: 0;">
                                            You can now close this tab.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Connection Status --}}
                            <div class="section">
                                <div class="status-indicator status-connected">
                                    <span>🟢</span>
                                    <span>Payment Provider Ready</span>
                                </div>
                            </div>

                            {{-- API Keys Section --}}
                            <div class="section">
                                <h2 class="section-title">API Configuration</h2>
                                <p class="help-text">Enter your Tap Payment API keys. These are only used when connecting your provider.</p>
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Test - Secret</label>
                                        <input 
                                            name="test_secretKey" 
                                            type="password" 
                                            value="{{ old('test_secretKey') }}" 
                                            placeholder="sk_test_xxx" 
                                            class="form-input"
                                        />
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Prod - Secret</label>
                                        <input 
                                            name="live_secretKey" 
                                            type="password" 
                                            value="{{ old('live_secretKey') }}" 
                                            placeholder="sk_live_xxx" 
                                            class="form-input"
                                        />
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Test - Public</label>
                                        <input 
                                            name="test_publishableKey" 
                                            type="text" 
                                            value="{{ old('test_publishableKey') }}" 
                                            placeholder="pk_test_xxx" 
                                            class="form-input"
                                        />
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Prod - Public Key</label>
                                        <input 
                                            name="live_publishableKey" 
                                            type="text" 
                                            value="{{ old('live_publishableKey') }}" 
                                            placeholder="pk_live_xxx" 
                                            class="form-input"
                                        />
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Test - API Key</label>
                                        <input 
                                            name="test_apiKey" 
                                            type="text" 
                                            value="{{ old('test_apiKey') }}" 
                                            placeholder="test_xxx" 
                                            class="form-input"
                                        />
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Prod - API Key</label>
                                        <input 
                                            name="live_apiKey" 
                                            type="text" 
                                            value="{{ old('live_apiKey') }}" 
                                            placeholder="live_xxx" 
                                            class="form-input"
                                        />
                                    </div>
                                </div>
                            </div>

                            {{-- Action Buttons --}}
                            <div class="action-buttons">
                                <button type="submit" name="action" value="connect" class="btn btn-primary">
                                    <span>🔗</span>
                                    Connect Provider
                                </button>
                            </div>

                            {{-- Error Messages --}}
                            @if (session('api_error'))
                                <div class="response-box response-error">
                                    <strong>❌ Error Response:</strong>
                                    <pre style="margin-top: 0.5rem; white-space: pre-wrap; font-family: 'Monaco', 'Menlo', monospace;">{{ session('api_error') }}</pre>
                                </div>
                            @endif
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </body>
        <script>
            // Initialize form functionality
            document.addEventListener('DOMContentLoaded', function() {
                // Set the information field if not already set
                const informationField = document.getElementById('information');
                if (!informationField.value) {
                    // Try to get from parent window or use current location
                    try {
                        informationField.value = window.parent.location.href;
                    } catch(e) {
                        informationField.value = window.location.href;
                    }
                }
                
                // Add form validation (only if form is visible, not in success state)
                const form = document.getElementById('paymentForm');
                const connectBtn = form.querySelector('button[value="connect"]');
                
                // Only add validation if connect button exists
                if (!connectBtn) {
                    return; // Success message is showing, no need for validation
                }
                
                // Connect button validation
                connectBtn.addEventListener('click', function(e) {
                    const liveApiKey = form.querySelector('input[name="live_apiKey"]').value;
                    const livePubKey = form.querySelector('input[name="live_publishableKey"]').value;
                    const liveSecretKey = form.querySelector('input[name="live_secretKey"]').value;
                    const testApiKey = form.querySelector('input[name="test_apiKey"]').value;
                    const testPubKey = form.querySelector('input[name="test_publishableKey"]').value;
                    const testSecretKey = form.querySelector('input[name="test_secretKey"]').value;
                    
                    // Require at least one set of keys (live or test)
                    const hasLiveKeys = liveApiKey && livePubKey && liveSecretKey;
                    const hasTestKeys = testApiKey && testPubKey && testSecretKey;
                    
                    if (!hasLiveKeys && !hasTestKeys) {
                        e.preventDefault();
                        alert('Please enter at least one set of API keys (Live or Test).');
                        return false;
                    }
                });
            });
        </script>
 
</html>
