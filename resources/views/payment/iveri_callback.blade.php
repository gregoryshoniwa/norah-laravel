<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Completing payment...</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { text-align: center; padding: 24px; }
        .spinner { width: 36px; height: 36px; border: 4px solid rgba(255,255,255,0.2); border-top-color: #fff; border-radius: 50%; animation: spin 0.9s linear infinite; margin: 0 auto 16px; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner"></div>
        <p>Finalising your payment...</p>
    </div>
    <script>
        (function () {
            var payload = {
                source: 'iveri',
                event: 'iveri:done',
                trace: @json($trace ?? null),
                status: @json($status ?? 'UNKNOWN')
            };
            try {
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage(payload, '*');
                }
            } catch (e) { /* cross-origin, ignored */ }
            setTimeout(function () { window.close(); }, 300);
        })();
    </script>
</body>
</html>
