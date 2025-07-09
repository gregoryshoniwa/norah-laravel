<?php
// Standalone payment result page exactly like working zimswitch/pay_result.php

// Load configuration from auth.json (exactly like working implementation)
$filePath = file_get_contents('../zimswitch/auth.json');
$authData = json_decode($filePath, true);

function request($bearer, $resourcePath, $baseUrl, $entityID) {
    $url = $baseUrl . $resourcePath;
    $url .= "?entityId=" . $entityID;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array($bearer)
    );
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $responseData = curl_exec($ch);
    if (curl_errno($ch)) {
        return curl_error($ch);
    }
    curl_close($ch);
    return $responseData;
}

$paymentResult = null;
$errorMessage = null;

// Working implementation expects resourcePath via GET (how EFTPay sends it)
if (isset($_GET['resourcePath'])) {
    $resourcePath = $_GET['resourcePath'];
    $responseData = request($authData['authorizationBearer'], $resourcePath, $authData['baseUrl'], $authData['entityId']);

    $result = json_decode($responseData);

    if ($result) {
        $resId = $result->{'id'};
        $resType = $result->{'paymentType'};
        $resBrand = $result->{'paymentBrand'};
        $resAmount = $result->{'amount'};
        $resCurrency = $result->{'currency'};
        $resResultDescription = $result->{'result'}->{'description'};
        $resResultCode = $result->{'result'}->{'code'};
        $resNdc = $result->{'ndc'};
        $resDescriptor = $result->{'descriptor'};
        $resResultDetailsExtendedDescription = $result->{'resultDetails'}->{'ExtendedDescription'} ?? '';

        // Convert to array format for compatibility with existing template
        $paymentResult = [
            'id' => $resId,
            'paymentType' => $resType,
            'paymentBrand' => $resBrand,
            'amount' => $resAmount,
            'currency' => $resCurrency,
            'result' => [
                'description' => $resResultDescription,
                'code' => $resResultCode
            ]
        ];
    } else {
        $errorMessage = "Failed to parse payment response";
    }
} else {
    $errorMessage = "No payment information received";
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
        <?php elseif (isset($paymentResult)): ?>
            <?php
            // Use the same success pattern as working implementation
            $isSuccess = isset($paymentResult['result']['code']) &&
                        preg_match("/^(000\.000\.|000\.100\.1|000\.[36])/", $paymentResult['result']['code']);
            ?>

            <?php if ($isSuccess): ?>
                <h3 class="success">Transaction successful</h3>
                <p>Reference Number: <?php echo htmlspecialchars($paymentResult['id']); ?></p>
                <p>Brand: <?php echo htmlspecialchars($paymentResult['paymentBrand']); ?></p>
                <p>Amount: <?php echo htmlspecialchars($paymentResult['amount'] . ' ' . $paymentResult['currency'] . ' (' . $paymentResult['paymentType'] . ')'); ?></p>
                <h3>Thank you for your donation!</h3>
                <button onclick="location.href='/';" class="button">HOME</button>
            <?php else: ?>
                <h3 class="error">Transaction failed</h3>
                <p>Please try again later or contact your bank and share the following information with them if the problem persists:</p>
                <p>Error code: <?php echo htmlspecialchars($paymentResult['result']['code']); ?></p>
                <p>Description: <?php echo htmlspecialchars($paymentResult['result']['description']); ?></p>
                <p>Brand: <?php echo htmlspecialchars($paymentResult['paymentBrand']); ?></p>
                <p>Transaction type: <?php echo htmlspecialchars($paymentResult['paymentType']); ?></p>
                <button onclick="location.href='/';" class="button">HOME</button>
            <?php endif; ?>
        <?php else: ?>
            <p class="error">Internal error. Contact your system administrator.</p>
            <button onclick="location.href='/';" class="button">HOME</button>
        <?php endif; ?>
    </div>
</body>
</html>
