<!DOCTYPE html>
<html>

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="mystyles.css">
	<link rel="icon" type="image/x-icon" href="/favicon.png">

	<script src="https://code.jquery.com/jquery.js" type="text/javascript"></script>
	<script type="text/javascript">
		function disableBack() { window.history.forward(); }
		setTimeout("disableBack()", 0);
		window.onunload = function () { null };
	</script>
	<title>Pay</title>
</head>
<body>
	<?php
	setcookie('cookie_eftcorp', 'https://eftpaygateway.com/', ['SameSite' => 'Strict']);
	$filePath = file_get_contents('auth.json');
	$authData = json_decode($filePath, true);

	$header_text = "";
	if ($_POST['paymode'] == "TEST_INTERNAL") {
		$header_text = 'This is a TEST:INTERNAL transaction. Do not use decimal values other than "00".';

	} else if ($_POST['paymode'] == "TEST_EXTERNAL") {
		$header_text = 'This is a TEST:EXTERNAL transaction. Do not use decimal values other than "00".';

	} else {
		$header_text = "This is a production site and funds will be deducted from your personal account when performing a transaction.";
	}


	function request_checkoutid($bearer, $entityID, $oppwaURL, $paytype, $currency, $amount, $paymode)
	{
		$data = "entityId=" . $entityID . "&amount=" . $amount . "&currency=" . $currency . "&paymentType=" . $paytype;

		//echo '*'.$data.'*';
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
			array(
				$bearer
			)
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

	$responseData = request_checkoutid($authData['authorizationBearer'], $authData['entityId'], $authData['oppwaUrl'], $authData['payType'], $_POST['currency'], $_POST['amount'], $_POST['paymode']);
	$decodedData = json_decode($responseData);

	?>

	<div class="container">
		<div class="row">
			<div class="column center">
				<div class="center_text">
					<h4>
						<?php echo $header_text; ?>
					</h4>
					You may use any TEST card in TEST mode.<br>
					You may use the following card details for testing: <br>
					Card number: 4242 4242 4242 4242 <br>
					Expiry Date: 12/24 (any future date) <br>
					Card holder: abcd (any string longer than three character)<br>
					CVV: 123 (any three digits)<br>
				</div>
			</div>

			<div class="column">
				<div class="center_text">
					<?php
					echo "Transaction details: Amount: " . $_POST['amount'] . " " . $_POST['currency'] . " (" . $authData['payType'] . ")<br> <br>";
					// VMC -----------------------------
					if ($_POST['paysource'] == "VMC") {
					?>
				</div>

				<script type="text/javascript">
					var wpwlOptions = {
						style: "card",
						brandDetection: true,
						showPlaceholders: false,
					}
				</script>
				<script async src="https://<?php echo $authData['checkoutUrl'] ?><?php echo $decodedData->id; ?>"></script>

				<form action="http://<?php echo $authData['shopperUrl']; ?>/pay_result.php" class="paymentWidgets" data-brands="VISA MASTER">
				</form>
				<div class="center">
					<button onclick="location.href='/';">CANCEL</button>
				</div>

				<?php
				} else
				// ZIMSWITCH/OTHER -----------------------------
				{
				?>

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

					<form action="http://<?php echo $authData['shopperUrl']; ?>/pay_result.php" class="paymentWidgets" data-brands="PRIVATE_LABEL">
					</form>
					<div class="center">
						<button onclick="location.href='/';" class="button">CANCEL</button>
					</div>

				<?php
				}
				?>
			</div>
		</div>
	</div>
</body>
</html>
