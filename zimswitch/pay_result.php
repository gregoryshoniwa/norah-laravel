<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="stylesheet" href="mystyles.css">
	<link rel="icon" type="image/x-icon" href="/favicon.png">
	<script type="text/javascript">
		function disableBack() { window.history.forward(); }
		setTimeout("disableBack()", 0);
		window.onunload = function () { null };
	</script>
	<title>Payment Response</title>
</head>

<body>
	<div class="container">

		<?php

		$filePath = file_get_contents('auth.json');
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

		//====================================================================== 
		// CODE ENTRY POINT 
		//======================================================================

		$resourcePath = $_GET['resourcePath'];
		$responseData = request($authData['authorizationBearer'], $resourcePath, $authData['baseUrl'], $authData['entityId']);

		$result = json_decode($responseData);

		$resId = $result->{'id'};
		$resType = $result->{'paymentType'};
		$resBrand = $result->{'paymentBrand'};
		$resAmount = $result->{'amount'};
		$resCurrency = $result->{'currency'};

		$resResultDescription = $result->{'result'}->{'description'};
		$resResultCode = $result->{'result'}->{'code'};

		$resNdc = $result->{'ndc'};
		$resDescriptor = $result->{'descriptor'};
		$resResultDetailsExtendedDescription = $result->{'resultDetails'}->{'ExtendedDescription'};
		$resResultDetailsAuthCode = $result->{'resultDetails'}->{'AuthCode'};
		$resResultDetailsConnectorTxID1 = $result->{'resultDetails'}->{'ConnectorTxID1'};
		$resResultDetailsConnectorTxID2 = $result->{'resultDetails'}->{'ConnectorTxID2'};
		$resResultDetailsConnectorTxID3 = $result->{'resultDetails'}->{'ConnectorTxID3'};
		$resResultDetailsAcquirerResponse = $result->{'resultDetails'}->{'AcquirerResponse'};
		$resResultDetailsCardholderInitiatedTransactionID = $result->{'resultDetails'}->{'CardholderInitiatedTransactionID'};

		// Useful for debugging to see all available fields: echo json_encode($result, JSON_PRETTY_PRINT);
		
		if (isset($resResultCode)) {
			if (preg_match("/^(000\.000\.|000\.100\.1|000\.[36])/", $resResultCode)) {
				?>
				<div class="center center_text">

					<?php
					echo "<h3>Transaction successful</h3>";
					echo "Reference Number: $resId" . '<br>';
					echo "Brand: $resBrand" . '<br>';
					echo "Amount: $resAmount $resCurrency ($resType)" . '<br>';
					echo "<h3>Thank you for your donation!</h3>" . '<br>' . '<br>';

					?>
					<button onclick="location.href='/';">HOME</button>
				</div>
				<?php
			} else {
				?>
				<div class="center center_text">
					<?php
					echo "<h3>Transaction failed</h3>";
					echo "Please try again later or contact your bank and share the following information with them if the problem persists:<br>";
					echo "Error code: $resResultCode" . '<br>';
					echo "Extended Description: $resResultDetailsExtendedDescription" . '<br>';
					echo "Description: $resResultDescription" . '<br>';
					echo "Trace Number: $resNdc" . '<br>';
					echo "Brand: $resBrand" . '<br>';
					echo "Transaction type: $resType" . '<br>' . '<br>';
					?>

					<button onclick="location.href='/';">HOME</button>
				</div>
				<?php
			}
		} else {
			echo '<br>Internal error. Contact your system administrator.<br>';
		}
		?>
	</div>
</body>

</html>
