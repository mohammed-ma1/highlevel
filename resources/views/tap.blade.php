{{-- resources/views/tap.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ config('app.name', 'Laravel') }} — Tap Card SDK v2</title>
</head>
<body>
  <h1>Tap Integration (Web Card SDK v2)</h1>

  <!-- Where the card UI will render -->
  <div id="card-sdk-id" style="max-width: 420px; margin: 12px 0;"></div>

  <!-- Submit / Tokenize -->
  <button id="tap-tokenize-btn" type="button">Pay / Tokenize</button>

  <!-- (Optional) Where we print the token result -->
  <pre id="tap-result" style="white-space:pre-wrap; background:#f6f6f6; padding:10px; border:1px solid #ddd;"></pre>

  <!-- Tap Web Card SDK v2 -->
  <script src="https://tap-sdks.b-cdn.net/card/1.0.2/index.js"></script>

  <script>
    // Pull SDK helpers
    const { renderTapCard, Theme, Currencies, Direction, Edges, Locale, tokenize } = window.CardSDK;

    // 1) Render the card
    const { unmount } = renderTapCard('card-sdk-id', {
      publicKey: 'pk_test_YhUjg9PNT8oDlKJ1aE2fMRz7', // <-- Your Tap PUBLIC key
      merchant: {
        id: 'merchant_id_here'           // <-- Your Tap Merchant ID
      },
      transaction: {
        amount: 1,                        // Example amount
        currency: Currencies.JOD          // Use your currency (e.g., JOD, SAR, USD)
      },
      // Optional but recommended customer info
      customer: {
        // id: 'cus_xxxxx',               // If you have a Tap customer ID
        name: [
          { lang: Locale.EN, first: 'Test', last: 'User' }
        ],
        nameOnCard: 'Test User',
        editable: true,
        contact: {
          email: 'test@example.com',
          phone: { countryCode: '962', number: '790000000' }
        }
      },
      // Show only the brands you’re enabled for in your Tap account
      acceptance: {
        supportedBrands: ['VISA', 'MASTERCARD', 'AMERICAN_EXPRESS', 'MADA'],
        supportedCards: "ALL" // "ALL" | ["DEBIT"] | ["CREDIT"]
      },
      fields: {
        cardHolder: true
      },
      addons: {
        displayPaymentBrands: true,
        loader: true,
        saveCard: true
      },
      interface: {
        locale: Locale.EN,
        theme: Theme.LIGHT,               // LIGHT | DARK
        edges: Edges.CURVED,              // SHARP | CURVED
        direction: Direction.LTR
      },

      // Helpful callbacks
      onReady: () => console.log('Tap Card: ready'),
      onFocus: () => console.log('Tap Card: focus'),
      onBinIdentification: data => console.log('BIN identified:', data),
      onValidInput: data => console.log('Valid input:', data),
      onInvalidInput: data => console.log('Invalid input:', data),
      onError: err => console.error('Tap Card error:', err),

      // When tokenization succeeds, you’ll get the Tap Token here
      onSuccess: (data) => {
        console.log('Token success:', data);
        document.getElementById('tap-result').textContent =
          'Tap Token:\n' + JSON.stringify(data, null, 2);

        // Example: POST token to your Laravel backend for creating a charge
        // fetch("{{ route('client.webhook') }}", {
        //   method: "POST",
        //   headers: {
        //     "Content-Type": "application/json",
        //     "X-CSRF-TOKEN": "{{ csrf_token() }}"
        //   },
        //   body: JSON.stringify({ token: data?.id })
        // }).then(r => r.json()).then(console.log).catch(console.error);
      }
    });

    // 2) Wire the button to call tokenize()
    document.getElementById('tap-tokenize-btn').addEventListener('click', () => {
      // Triggers SDK to validate inputs & create Tap token
      tokenize();
    });
  </script>
</body>
</html>
