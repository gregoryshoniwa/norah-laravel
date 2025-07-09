<?php
// Standalone payment result page exactly like working zimswitch/pay_result.php

// Load configuration from auth.json (exactly like working implementation)
$filePath = file_get_contents('../zimswitch/auth.json');
$authData = json_decode($filePath, true);

$paymentResult = null;
$errorMessage = null;

if (isset($_POST['resourcePath'])) {
    $resourcePath = $_POST['resourcePath'];

    // Get payment status using cURL exactly like working implementation
    $url = $authData['baseUrl'] . $resourcePath;
    $url .= "?entityId=" . $authData['entityId'];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($authData['authorizationBearer']));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $responseData = curl_exec($ch);

    if (curl_errno($ch)) {
        $errorMessage = curl_error($ch);
    } else {
        $paymentResult = json_decode($responseData, true);
    }
    curl_close($ch);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Result</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        .button {
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Payment Result</h2>

        <?php if ($errorMessage): ?>
            <div class="error">
                <h3>Error</h3>
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
        <?php elseif ($paymentResult): ?>
            <?php
            $isSuccess = isset($paymentResult['result']['code']) &&
                        in_array($paymentResult['result']['code'], [
                            '000.100.110', // Transaction successfully processed
                            '000.100.111', // Transaction successfully processed, but risk management review required
                            '000.100.112', // Transaction successfully processed, but risk management review required
                            '000.000.000'  // Transaction successfully processed
                        ]);
            ?>

            <?php if ($isSuccess): ?>
                <div class="success">
                    <h3>✅ Payment Successful!</h3>
                    <p>Your payment has been processed successfully.</p>
                    <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($paymentResult['id'] ?? 'N/A'); ?></p>
                    <p><strong>Result:</strong> <?php echo htmlspecialchars($paymentResult['result']['description'] ?? 'Success'); ?></p>
                </div>
            <?php else: ?>
                <div class="error">
                    <h3>❌ Payment Failed</h3>
                    <p>Your payment could not be processed.</p>
                    <p><strong>Error:</strong> <?php echo htmlspecialchars($paymentResult['result']['description'] ?? 'Unknown error'); ?></p>
                    <p><strong>Code:</strong> <?php echo htmlspecialchars($paymentResult['result']['code'] ?? 'N/A'); ?></p>
                </div>
            <?php endif; ?>

            <details style="margin-top: 20px;">
                <summary>Technical Details</summary>
                <pre style="text-align: left; background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;">
<?php echo json_encode($paymentResult, JSON_PRETTY_PRINT); ?>
                </pre>
            </details>
        <?php else: ?>
            <div class="error">
                <h3>No Payment Data</h3>
                <p>No payment information was received.</p>
            </div>
        <?php endif; ?>

        <button onclick="window.close();" class="button">Close Window</button>
    </div>
</body>
</html>
