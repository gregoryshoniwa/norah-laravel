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

// Check if this is a refresh/direct access without payment data
$isDirectAccess = empty($_GET) && empty($_POST);
$hasValidResourcePath = isset($_GET['resourcePath']) && !empty($_GET['resourcePath']);

if ($isDirectAccess) {
    // User refreshed or accessed directly - show session expired message
    $errorMessage = "session_expired";
} elseif ($hasValidResourcePath) {
    // Valid payment callback - process normally
    $resourcePath = $_GET['resourcePath'];

    try {
        $responseData = request($authData['authorizationBearer'], $resourcePath, $authData['baseUrl'], $authData['entityId']);

        if ($responseData && !is_string($responseData)) {
            // If responseData is an error string from curl_error
            $errorMessage = "Connection error: " . $responseData;
        } else {
            $result = json_decode($responseData);
            // echo "<script>console.log('Result: " . json_encode($result) . "');</script>";
            if ($result && isset($result->id)) {
                // Safely extract properties with null coalescing
                $resId = $result->id ?? 'N/A';
                $resType = $result->paymentType ?? 'N/A';
                $resBrand = $result->paymentBrand ?? 'N/A';
                $resAmount = $result->amount ?? '0.00';
                $resCurrency = $result->currency ?? 'USD';
                $resResultDescription = $result->result->description ?? 'Unknown status';
                $resResultCode = $result->result->code ?? '999.999.999';
                $resResultDetailsExtendedDescription = $result->{'resultDetails'}->{'ExtendedDescription'};
                // Convert to array format for compatibility with existing template
                $paymentResult = [
                    'id' => $resId,
                    'paymentType' => $resType,
                    'paymentBrand' => $resBrand,
                    'amount' => $resAmount,
                    'currency' => $resCurrency,
                    'result' => [
                        'description' => $resResultDescription,
                        'extendedDescription' => $resResultDetailsExtendedDescription,
                        'code' => $resResultCode
                    ]
                ];

            } else {
                // Check if the response contains an error message about expired session
                $responseArray = json_decode($responseData, true);
                if (isset($responseArray['result']['description']) &&
                    strpos($responseArray['result']['description'], 'No payment session found') !== false) {
                    $errorMessage = "session_expired";
                } else {
                    $errorMessage = "Invalid payment response received";
                }
            }
        }
    } catch (Exception $e) {
        $errorMessage = "Error processing payment: " . $e->getMessage();
    }
} else {
    $errorMessage = "No payment information received";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Result</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        'primary-dark': '#3730a3',
                    },
                    animation: {
                        'gradient': 'gradient 15s ease infinite',
                        'fade-in': 'fadeIn 0.5s ease-in',
                        'slide-up': 'slideUp 0.6s ease-out',
                        'bounce-in': 'bounceIn 0.8s ease-out',
                    },
                    keyframes: {
                        gradient: {
                            '0%, 100%': {
                                'background-size': '200% 200%',
                                'background-position': 'left center'
                            },
                            '50%': {
                                'background-size': '200% 200%',
                                'background-position': 'right center'
                            }
                        },
                        fadeIn: {
                            '0%': { opacity: '0' },
                            '100%': { opacity: '1' }
                        },
                        slideUp: {
                            '0%': {
                                transform: 'translateY(30px)',
                                opacity: '0'
                            },
                            '100%': {
                                transform: 'translateY(0)',
                                opacity: '1'
                            }
                        },
                        bounceIn: {
                            '0%': {
                                transform: 'scale(0.3)',
                                opacity: '0'
                            },
                            '50%': {
                                transform: 'scale(1.05)',
                                opacity: '1'
                            },
                            '70%': {
                                transform: 'scale(0.9)',
                            },
                            '100%': {
                                transform: 'scale(1)',
                            }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .animate-gradient {
            background: linear-gradient(-45deg, #0f172a, #1e293b, #4f46e5, #6366f1);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }

        .glass-effect {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }

        .success-icon {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .error-icon {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-slate-800 to-indigo-600 animate-gradient">
    <!-- Background Elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-purple-400 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-blue-400 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
        <div class="absolute top-40 left-1/2 w-80 h-80 bg-indigo-400 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-4000"></div>
    </div>

    <div class="relative min-h-screen py-8 px-4 sm:px-6 lg:px-8 flex items-center justify-center">
        <!-- Main Result Card -->
        <div class="max-w-md w-full bg-white/90 glass-effect rounded-2xl shadow-2xl overflow-hidden animate-slide-up">

            <?php
            // Determine success status
            $isSuccess = false;
            $resultData = null;

            if (!$errorMessage && isset($paymentResult)) {
                $isSuccess = isset($paymentResult['result']['code']) &&
                            preg_match("/^(000\.000\.|000\.100\.1|000\.[36])/", $paymentResult['result']['code']);
                $resultData = $paymentResult;
            }
            ?>

            <!-- Header with Status -->
            <div class="p-6 <?php
                if ($errorMessage === "session_expired") {
                    echo 'bg-gradient-to-r from-yellow-500 to-orange-600';
                } elseif ($isSuccess) {
                    echo 'bg-gradient-to-r from-green-500 to-emerald-600';
                } else {
                    echo 'bg-gradient-to-r from-red-500 to-pink-600';
                }
            ?> text-white">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-16 h-16 <?php
                        if ($errorMessage === "session_expired") {
                            echo 'bg-yellow-600';
                        } elseif ($isSuccess) {
                            echo 'success-icon bg-green-600';
                        } else {
                            echo 'error-icon bg-red-600';
                        }
                    ?> rounded-full flex items-center justify-center animate-bounce-in">
                        <?php if ($errorMessage === "session_expired"): ?>
                            <!-- Session Expired Clock Icon -->
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        <?php elseif ($isSuccess): ?>
                            <!-- Success Checkmark -->
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        <?php else: ?>
                            <!-- Error X -->
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                </div>

                                <h1 class="text-2xl font-bold text-center text-white mb-2">
                    <?php if ($errorMessage === "session_expired"): ?>
                        Session Expired
                    <?php elseif ($errorMessage): ?>
                        System Error
                    <?php elseif ($isSuccess): ?>
                        Payment Successful!
                    <?php else: ?>
                        Payment Failed
                    <?php endif; ?>
                </h1>

                <p class="text-center text-white/90 text-sm">
                    <?php if ($errorMessage === "session_expired"): ?>
                        This payment session is no longer available
                    <?php elseif ($errorMessage): ?>
                        An error occurred processing your payment
                    <?php elseif ($isSuccess): ?>
                        Your transaction has been completed successfully
                    <?php else: ?>
                        Your payment could not be processed
                    <?php endif; ?>
                </p>
            </div>

            <!-- Payment Details -->
            <div class="p-6 space-y-4">
                <?php if ($errorMessage === "session_expired"): ?>
                    <!-- Session Expired Message -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-yellow-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-medium text-yellow-800">Session Expired</h3>
                                <p class="text-sm text-yellow-700 mt-1">This payment session has expired or is no longer valid.</p>
                            </div>
                        </div>
                        <div class="mt-4 p-3 bg-yellow-100 rounded-md">
                            <p class="text-sm text-yellow-800">
                                <strong>What happened?</strong> Payment sessions expire after 30 minutes for security reasons. If you completed a payment, it may have already been processed.
                            </p>
                        </div>
                    </div>
                <?php elseif ($errorMessage): ?>
                    <!-- Other Error Messages -->
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-medium text-red-800">Error Details</h3>
                                <p class="text-sm text-red-700 mt-1"><?php echo htmlspecialchars($errorMessage); ?></p>
                            </div>
                        </div>
                    </div>

                <?php elseif (isset($resultData)): ?>
                    <?php if ($isSuccess): ?>
                        <!-- Success - Redirect to Laravel for processing -->
                        <div class="space-y-3">
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                <h3 class="text-lg font-semibold text-green-800 mb-3">Processing Payment...</h3>
                                <div class="text-center">
                                    <div class="inline-flex items-center">
                                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-green-700">Finalizing your transaction...</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                            // Redirect to Laravel route for processing
                            setTimeout(function() {
                                const paymentData = <?php echo json_encode($resultData); ?>;
                                const resourcePath = '<?php echo $_GET['resourcePath'] ?? ''; ?>';

                                // Create form to submit data to Laravel
                                const form = document.createElement('form');
                                form.method = 'POST';
                                form.action = '/api/v1/transactions/zimswitch-finalize';

                                // Add payment data
                                const paymentDataInput = document.createElement('input');
                                paymentDataInput.type = 'hidden';
                                paymentDataInput.name = 'payment_data';
                                paymentDataInput.value = JSON.stringify(paymentData);
                                form.appendChild(paymentDataInput);

                                const resourcePathInput = document.createElement('input');
                                resourcePathInput.type = 'hidden';
                                resourcePathInput.name = 'resource_path';
                                resourcePathInput.value = resourcePath;
                                form.appendChild(resourcePathInput);

                                // Add CSRF token if available
                                const csrfToken = document.querySelector('meta[name="csrf-token"]');
                                if (csrfToken) {
                                    const csrfInput = document.createElement('input');
                                    csrfInput.type = 'hidden';
                                    csrfInput.name = '_token';
                                    csrfInput.value = csrfToken.getAttribute('content');
                                    form.appendChild(csrfInput);
                                }

                                document.body.appendChild(form);
                                form.submit();
                            }, 2000); // 2 second delay to show processing message
                        </script>

                    <?php else: ?>
                        <!-- Failure Details -->
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                            <h3 class="text-lg font-semibold text-red-800 mb-3">Transaction Failed</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-red-600">Error Code:</span>
                                    <span class="font-mono text-red-800"><?php echo htmlspecialchars($resultData['result']['code']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-red-600">Transaction:</span>
                                    <span class="text-red-800"><?php echo htmlspecialchars($resultData['currency']); ?> <?php echo htmlspecialchars($resultData['amount']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-red-600">Description:</span>
                                    <span class="text-red-800"><?php
                                        // Show ExtendedDescription if available, otherwise fall back to description
                                        if (isset($resultData['result']['extendedDescription']) && !empty($resultData['result']['extendedDescription'])) {
                                            echo htmlspecialchars($resultData['result']['extendedDescription']);
                                        } else {
                                            echo htmlspecialchars($resultData['result']['description']);
                                        }
                                    ?></span>
                                </div>

                                <!-- <div class="flex justify-between">
                                    <span class="text-red-600">Payment Method:</span>
                                    <span class="text-red-800"><?php echo htmlspecialchars($resultData['paymentBrand']); ?></span>
                                </div> -->
                            </div>

                            <div class="mt-4 p-3 bg-red-100 rounded-md">
                                <p class="text-sm text-red-800">
                                    <strong>Need Help?</strong> Please contact your bank with the error code above, or try again later.
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- System Error -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-gray-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-medium text-gray-800">System Error</h3>
                                <p class="text-sm text-gray-600 mt-1">Internal error. Please contact your system administrator.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="px-6 pb-6">
                <div class="flex space-x-3">
                    <?php if ($errorMessage === "session_expired"): ?>
                        <!-- Session Expired - Only Close Option -->
                        <button
                            onclick="window.close()"
                            class="flex-1 bg-gradient-to-r from-gray-500 to-gray-600 hover:from-gray-600 hover:to-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 shadow-lg"
                        >
                            Close Window
                        </button>
                    <?php else: ?>
                        <!-- Normal Buttons -->
                        <button
                            onclick="location.href='/'"
                            class="flex-1 bg-gradient-to-r from-primary to-indigo-600 hover:from-primary-dark hover:to-indigo-700 text-white font-semibold py-3 px-6 rounded-lg transition-all duration-200 transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 shadow-lg"
                        >
                            Return Home
                        </button>


                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Additional Status Message -->
        <!-- <div class="absolute bottom-8 left-1/2 transform -translate-x-1/2">
            <p class="text-white/70 text-sm text-center">
                Need help? Contact <a href="mailto:support@example.com" class="text-white underline hover:text-white/90">support@example.com</a>
            </p>
        </div> -->
    </div>

    <script>
        // Add subtle animations on load
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-redirect on successful payment after 10 seconds
            <?php if ($isSuccess): ?>
            setTimeout(function() {
                const button = document.querySelector('button[onclick="location.href=\'/\'"]');
                if (button && !document.hidden) {
                    button.style.transform = 'scale(1.05)';
                    button.style.boxShadow = '0 0 20px rgba(79, 70, 229, 0.5)';
                    setTimeout(() => {
                        button.style.transform = '';
                        button.style.boxShadow = '';
                    }, 500);
                }
            }, 8000);
            <?php endif; ?>
        });
    </script>
</body>
</html>
