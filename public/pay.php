<?php
// Standalone payment page exactly like working zimswitch/pay.php
setcookie('cookie_eftcorp', 'https://eftpaygateway.com/', ['SameSite' => 'Strict']);

// Load configuration from auth.json (exactly like working implementation)
$filePath = file_get_contents('../zimswitch/auth.json');
$authData = json_decode($filePath, true);

// Get payment parameters from URL or use defaults for testing
$amount = $_GET['amount'] ?? '1.00';
$currency = $_GET['currency'] ?? 'USD';
$paymode = $_GET['paymode'] ?? 'TEST_EXTERNAL';
$paysource = $_GET['paysource'] ?? 'ZIMSWITCH';

$header_text = "";
if ($paymode == "TEST_INTERNAL") {
    $header_text = 'This is a TEST:INTERNAL transaction. Do not use decimal values other than "00".';
} else if ($paymode == "TEST_EXTERNAL") {
    $header_text = 'This is a TEST:EXTERNAL transaction. Do not use decimal values other than "00".';
} else {
    $header_text = "This is a production site and funds will be deducted from your personal account when performing a transaction.";
}

// Function to request checkout ID (exactly like working implementation)
function request_checkoutid($bearer, $entityID, $oppwaURL, $paytype, $currency, $amount, $paymode)
{
    $data = "entityId=" . $entityID . "&amount=" . $amount . "&currency=" . $currency . "&paymentType=" . $paytype;

    if ($paymode == "TEST_INTERNAL") {
        $data = $data . "&testMode=INTERNAL";
    } else if ($paymode == "TEST_EXTERNAL") {
        $data = $data . "&testMode=EXTERNAL";
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $oppwaURL);
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array($bearer)
    );
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $responseData = curl_exec($ch);
    if (curl_errno($ch)) {
        return curl_error($ch);
    }
    curl_close($ch);
    return $responseData;
}

// Create checkout using the exact same method as working implementation
$responseData = request_checkoutid($authData['authorizationBearer'], $authData['entityId'], $authData['oppwaUrl'], $authData['payType'], $currency, $amount, $paymode);
$decodedData = json_decode($responseData);

// Checkout created successfully
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/x-icon" href="/favicon.png">
    <script src="https://code.jquery.com/jquery.js" type="text/javascript"></script>
    <script type="text/javascript">
        function disableBack() { window.history.forward(); }
        setTimeout("disableBack()", 0);
        window.onunload = function () { null };
    </script>
    <title>ZimSwitch Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .center {
            text-align: center;
        }
        .center_text {
            text-align: center;
            margin-bottom: 20px;
        }
        .button {
            padding: 10px 20px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .button:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="center_text">
            <h4><?php echo $header_text; ?></h4>
            You may use any TEST card in TEST mode.<br>
            You may use the following card details for testing: <br>
            Card number: 4242 4242 4242 4242 <br>
            Expiry Date: 12/24 (any future date) <br>
            Card holder: abcd (any string longer than three character)<br>
            CVV: 123 (any three digits)<br>
        </div>

        <div class="center_text">
            <?php
            echo "Transaction details: Amount: " . $amount . " " . $currency . " (" . $authData['payType'] . ")<br><br>";
            ?>
        </div>

        <!-- ZimSwitch Payment Widget (exactly like working implementation) -->
        <script type="text/javascript">
            var wpwlOptions = {
                style: "plain",
                brandDetection: false,
                showPlaceholders: false,
                onReady: function () {
                    $('.wpwl-group-brand').before("<img src='http://www.zimswitchonline.co.zw/wp-content/uploads/2022/06/favicon.1ee90efd.svg' width='200' style='vertical-align:middle;margin:50px 50px'></img>");
                    $('.wpwl-control-brand option[value="PRIVATE_LABEL"]').text("ZimSwitch");
                    $('.wpwl-control-card');
                    $('.wpwl-label-cardNumber').text("ZimSwitch Card");
                }
            }
        </script>

        <script async src="https://<?php echo $authData['checkoutUrl'] ?><?php echo $decodedData->id; ?>"></script>

        <form action="pay_result.php" class="paymentWidgets" data-brands="PRIVATE_LABEL">
        </form>

        <div class="center">
            <button onclick="window.close();" class="button">CANCEL</button>
        </div>
    </div>
</body>
</html>
