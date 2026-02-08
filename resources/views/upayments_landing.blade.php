<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        <style>
            * { box-sizing: border-box; }
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
                min-height: 100vh;
                margin: 0;
                padding: 0;
                color: #0f172a;
                line-height: 1.6;
            }
            .container { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; }
            .card {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15), 0 10px 10px -5px rgba(0,0,0,0.05);
                overflow: hidden;
                border: 1px solid rgba(255,255,255,0.2);
            }
            .card-header {
                background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
                color: white;
                padding: 2rem;
                text-align: center;
            }
            .card-header h1 { font-size: 1.875rem; font-weight: 700; margin: 0 0 0.5rem 0; letter-spacing: -0.025em; }
            .card-header p { font-size: 1rem; opacity: 0.95; margin: 0; font-weight: 400; }
            .card-body { padding: 2rem; }
            .section { margin-bottom: 2.5rem; }
            .section-title {
                font-size: 1.125rem;
                font-weight: 600;
                color: #0f172a;
                margin-bottom: 1rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }
            .section-title::before {
                content: '';
                width: 4px;
                height: 20px;
                background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
                border-radius: 2px;
            }
            .help-text { font-size: 0.75rem; color: #64748b; margin-top: 0.5rem; line-height: 1.4; }
            .mode-selector { display: flex; gap: 1rem; margin-bottom: 1.5rem; }
            .mode-option { flex: 1; position: relative; }
            .mode-option input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
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
                border-color: #2563eb;
                background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%);
                color: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
            }
            .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
            .form-group { display: flex; flex-direction: column; }
            .form-label { font-size: 0.875rem; font-weight: 500; color: #334155; margin-bottom: 0.5rem; }
            .form-input {
                padding: 0.875rem 1rem;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.875rem;
                transition: all 0.2s ease;
                background: #fafafa;
            }
            .form-input:focus { outline: none; border-color: #2563eb; background: white; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10); }
            .action-buttons { display: flex; gap: 1rem; margin-top: 2rem; flex-wrap: wrap; }
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
                min-width: 160px;
            }
            .btn-primary { background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 100%); color: white; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25); }
            .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35); }
            .response-box { margin-top: 1.5rem; padding: 1rem; border-radius: 8px; font-size: 0.875rem; border-left: 4px solid; }
            .response-success { background: #f0fdf4; border-color: #22c55e; color: #14532d; }
            .response-error { background: #fef2f2; border-color: #ef4444; color: #7f1d1d; }
            .status-indicator {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                border-radius: 999px;
                font-size: 0.75rem;
                font-weight: 500;
                margin-bottom: 1rem;
                background: #eff6ff;
                color: #1e40af;
                border: 1px solid #bfdbfe;
            }
            @media (max-width: 768px) {
                .container { padding: 1rem; }
                .card-body { padding: 1.5rem; }
                .form-grid { grid-template-columns: 1fr; }
                .action-buttons { flex-direction: column; }
                .mode-selector { flex-direction: column; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="card-header">
                    <h1>🔗 UPayments Integration</h1>
                    <p>Connect your payment provider</p>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('upayments.provider.connect_or_disconnect') }}" id="upaymentsForm">
                        @csrf
                        <input type="hidden" id="information" name="information" value="{{ old('information', request()->input('information')) }}">

                        @if (session('success'))
                            <div class="section">
                                <div class="response-box response-success" style="text-align: center; padding: 2rem;">
                                    <div style="font-size: 3rem; margin-bottom: 1rem;">✅</div>
                                    <h2 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem;">Connection Successful!</h2>
                                    <p style="font-size: 1rem; margin-bottom: 1.5rem;">
                                        {{ session('message') ?? 'Provider config created/updated successfully' }}
                                    </p>
                                    @if (session('locationId'))
                                        <p style="font-size: 0.875rem; color: #334155; margin-bottom: 2rem;">
                                            Location ID: <strong>{{ session('locationId') }}</strong>
                                        </p>
                                    @endif
                                    <div style="background: #f0fdf4; border: 2px solid #22c55e; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem;">
                                        <p style="font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem;">All done!</p>
                                        <p style="font-size: 1rem; margin: 0;">You can now close this tab.</p>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="section">
                                <div class="status-indicator">
                                    <span>🟢</span>
                                    <span>Payment Provider Ready</span>
                                </div>
                            </div>

                            <div class="section">
                                <h2 class="section-title">Payment Mode</h2>
                                <p class="help-text">Select whether you want to use Live or Test mode for UPayments.</p>

                                <div class="mode-selector">
                                    <div class="mode-option">
                                        <input type="radio" id="mode_test" name="upayments_mode" value="test" {{ old('upayments_mode', 'test') === 'test' ? 'checked' : '' }}>
                                        <label for="mode_test">🧪 Test Mode</label>
                                    </div>
                                    <div class="mode-option">
                                        <input type="radio" id="mode_live" name="upayments_mode" value="live" {{ old('upayments_mode') === 'live' ? 'checked' : '' }}>
                                        <label for="mode_live">🚀 Live Mode</label>
                                    </div>
                                </div>
                            </div>

                            <div class="section">
                                <h2 class="section-title">API Configuration</h2>
                                <p class="help-text">Enter your UPayments API tokens. These are only used when connecting your provider.</p>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Test Token</label>
                                        <input
                                            name="upayments_test_token"
                                            type="password"
                                            placeholder="e.g. jtest123"
                                            class="form-input"
                                            autocomplete="off"
                                        />
                                    </div>

                                    <div class="form-group">
                                        <label class="form-label">Live Token</label>
                                        <input
                                            name="upayments_live_token"
                                            type="password"
                                            placeholder="Your live token"
                                            class="form-input"
                                            autocomplete="off"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div class="action-buttons">
                                <button type="submit" name="action" value="connect" class="btn btn-primary">
                                    <span>🔗</span>
                                    Connect Provider
                                </button>
                            </div>

                            @if (session('api_error'))
                                <div class="response-box response-error">
                                    <strong>Error Response:</strong>
                                    <pre style="margin-top: 0.5rem; white-space: pre-wrap; font-family: 'Monaco','Menlo',monospace;">{{ session('api_error') }}</pre>
                                </div>
                            @endif
                        @endif
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const informationField = document.getElementById('information');
                if (!informationField.value) {
                    try {
                        informationField.value = window.parent.location.href;
                    } catch (e) {
                        informationField.value = window.location.href;
                    }
                }
            });
        </script>
    </body>
</html>

