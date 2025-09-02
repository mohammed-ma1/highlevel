<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        <!-- Styles / Scripts -->
      
            <style>
                /* General Layout */
body {
    font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
    background-color: #fdfdfc;
    color: #1b1b18;
}

.container {
    width: 100%;
    max-width: 720px;
    margin: 0 auto;
    padding: 2rem;
}

header {
    text-align: center;
    padding: 1.5rem 0;
    border-bottom: 1px solid #e3e3e0;
    margin-bottom: 2rem;
}

header h1 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1b1b18;
}

header p {
    font-size: 13px;
    color: #706f6c;
}

/* Form Styles */
form {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06);
    padding: 2rem;
    border: 1px solid #e3e3e0;
    display: flex;
    flex-direction: column;
}

form section {
    margin-bottom: 2rem;
}

form h2 {
    font-size: 1rem;
    font-weight: 500;
    margin-bottom: 1rem;
}

form label {
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
    color: #1b1b18;
}

form input {
    width: 100%;
    padding: 0.75rem;
    font-size: 0.875rem;
    border-radius: 8px;
    border: 1px solid #e3e3e0;
    background-color: white;
    margin-bottom: 1rem;
    color: #1b1b18;
    outline: none;
}

form input:focus {
    border-color: #007aff;
    box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.2);
}

/* Button Styles */
button {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 8px;
    margin-top: 1rem;
    cursor: pointer;
    transition: all 0.3s;
}

button.connect {
    background-color: #1b1b18;
    color: white;
    border: 1px solid #1b1b18;
}

button.connect:hover {
    background-color: black;
    border: 1px solid black;
}

button.disconnect {
    background-color: white;
    color: #1b1b18;
    border: 1px solid #1b1b18;
}

button.disconnect:hover {
    background-color: #1b1b18;
    color: white;
}

/* Error and Response Boxes */
.response-box, .error-box {
    padding: 1rem;
    margin-top: 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
}

.response-box {
    background-color: #f0f9ff;
    border: 1px solid #e0f7fa;
    color: #0077b6;
}

.error-box {
    background-color: #fef2f2;
    border: 1px solid #f7dad9;
    color: #ff4c4c;
}

/* Footer Text */
footer {
    font-size: 0.75rem;
    color: #706f6c;
    text-align: center;
    margin-top: 2rem;
}

            </style>
    </head>
    <body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
        <header class="w-full lg:max-w-4xl max-w-[335px] text-sm mb-6 not-has-[nav]:hidden">
            @if (Route::has('login'))
                <nav class="flex items-center justify-end gap-4">
                    @auth
                        <a
                            href="{{ url('/dashboard') }}"
                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal"
                        >
                            Dashboard
                        </a>
                    @else
                        <a
                            href="{{ route('login') }}"
                            class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] text-[#1b1b18] border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm text-sm leading-normal"
                        >
                            Log in
                        </a>

                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="inline-block px-5 py-1.5 dark:text-[#EDEDEC] border-[#19140035] hover:border-[#1915014a] border text-[#1b1b18] dark:border-[#3E3E3A] dark:hover:border-[#62605b] rounded-sm text-sm leading-normal">
                                Register
                            </a>
                        @endif
                    @endauth
                </nav>
            @endif
        </header>
        <div class="flex items-center justify-center w-full transition-opacity opacity-100 duration-750 lg:grow starting:opacity-0">
        <main class="w-full flex justify-center">
            <div class="w-full max-w-[560px] lg:max-w-[720px]">
                <form method="POST" action="{{ route('provider.connect_or_disconnect') }}" class="rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] shadow-[0_1px_2px_rgba(0,0,0,.06)] overflow-hidden">
                    @csrf
                    {{-- Header --}}
                    <div class="px-6 py-5 lg:px-8 lg:py-6 border-b border-[#e3e3e0] dark:border-[#3E3E3A] bg-[#FDFDFC] dark:bg-[#0a0a0a]">
                        <h1 class="text-xl font-medium dark:text-[#EDEDEC]">Tap Payment • Custom Provider</h1>
                        <p class="mt-1 text-[13px] text-[#706f6c] dark:text-[#A1A09A]">
                            Connect or disconnect your payment provider. The four keys are only used when you click <strong>Connect</strong>.
                        </p>
                    </div>

                    {{-- Base credentials --}}
                    <div class="px-6 py-6 lg:px-8 lg:py-8 space-y-6">
                    

                        {{-- Connect keys --}}
                        <section>
                            <div class="flex items-center justify-between mb-3">
                                <h2 class="text-sm font-medium dark:text-[#EDEDEC]">Connect Keys (required only for <span class="underline underline-offset-4">Connect</span>)</h2>
                              
                            </div>
<input type="hidden" id="information" name="information">

                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                    <label class="block text-sm mb-1 dark:text-[#EDEDEC]">Live API Key</label>
                                    <input name="live_apiKey" type="text" value="{{ old('live_apiKey') }}" placeholder="live_xxx" class="w-full rounded-md px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500" />
                                </div>
                                <div>
                                    <label class="block text-sm mb-1 dark:text-[#EDEDEC]">Live Publishable Key</label>
                                    <input name="live_publishableKey" type="text" value="{{ old('live_publishableKey') }}" placeholder="pk_live_xxx" class="w-full rounded-md px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500" />
                                </div>
                                <div>
                                    <label class="block text-sm mb-1 dark:text-[#EDEDEC]">Test API Key</label>
                                    <input name="test_apiKey" type="text" value="{{ old('test_apiKey') }}" placeholder="test_xxx" class="w-full rounded-md px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500" />
                                </div>
                                <div>
                                    <label class="block text-sm mb-1 dark:text-[#EDEDEC]">Test Publishable Key</label>
                                    <input name="test_publishableKey" type="text" value="{{ old('test_publishableKey') }}" placeholder="pk_test_xxx" class="w-full rounded-md px-3 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#0a0a0a] text-sm focus:outline-none focus:ring-2 focus:ring-sky-500/30 focus:border-sky-500" />
                                </div>
                            </div>
                        </section>

                        {{-- Actions --}}
                        <div class="flex items-center gap-3 pt-2">
                            <button
                                type="submit"
                                name="action"
                                value="connect"
                                class="inline-flex items-center justify-center px-5 py-2 rounded-md text-sm font-medium bg-[#1b1b18] text-white border border-black hover:bg-black hover:border-black dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:bg-white dark:hover:border-white transition-all"
                            >
                                Connect
                            </button>

                            <button
                                type="submit"
                                name="action"
                                value="disconnect"
                                class="inline-flex items-center justify-center px-5 py-2 rounded-md text-sm font-medium bg-white text-[#1b1b18] border border-[#1b1b18] hover:bg-[#1b1b18] hover:text-white dark:bg-[#0a0a0a] dark:text-[#EDEDEC] dark:hover:bg-white dark:hover:text-[#1C1C1A] transition-all"
                            >
                                Disconnect
                            </button>
                        </div>

                        {{-- Responses --}}
                        @if (session('api_response'))
                            <div class="mt-2 rounded-md border border-[#dbdbd7] bg-[#FDFDFC] dark:border-[#3E3E3A] p-3">
                                <div class="text-sm font-medium mb-1 dark:text-[#EDEDEC]">Response</div>
                                <pre class="text-[13px] text-[#1b1b18] dark:text-[#EDEDEC] whitespace-pre-wrap break-all">{{ json_encode(session('api_response'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </div>
                        @endif

                        @if (session('api_error'))
                            <div class="mt-2 rounded-md border border-[#f53003] bg-[#fff2f2] dark:bg-[#1D0002] p-3">
                                <div class="text-sm font-medium text-[#f53003]">Error</div>
                                <pre class="text-[13px] text-[#f53003] whitespace-pre-wrap break-all">{{ session('api_error') }}</pre>
                            </div>
                        @endif
                    </div>
                </form>

               
            </div>
        </main>


        </div>

        @if (Route::has('login'))
            <div class="h-14.5 hidden lg:block"></div>
        @endif
    </body>
 <script>
  // If you rendered state from the server into the page:
  const information = JSON.parse(`@json(request()->input('information'))`);
  document.getElementById('information').value = information;
          console.log('%c [  ]-280', 'font-size:13px; background:pink; color:#bf2c9f;',wnidow.parent.location.href )

</script>
 
</html>
