<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZimswitchPaymentService
{
    protected $baseUrl;
    protected $entityId;
    protected $authToken;
    protected $paymentBrand;

    public function __construct()
    {
        $this->baseUrl = config('services.zimswitch.url');
        $this->entityId = config('services.zimswitch.entity_id');
        $this->authToken = config('services.zimswitch.auth_token');
        $this->paymentBrand = config('services.zimswitch.payment_brand');
    }

    /**
     * Prepare a checkout for Zimswitch payment
     * Creates checkout using cURL and returns data for Vue.js integration
     *
     * @param array $data
     * @return array
     */
    public function prepareCheckout($data)
    {
        try {
            // Load auth configuration like the working implementation
            $authJsonPath = base_path('zimswitch/auth.json');
            if (!file_exists($authJsonPath)) {
                throw new \Exception('Authentication configuration file not found');
            }

            $authConfig = json_decode(file_get_contents($authJsonPath), true);
            if (!$authConfig) {
                throw new \Exception('Invalid authentication configuration');
            }

            // Prepare checkout request using cURL exactly like working implementation
            $amount = $data['amount'] ?? '1.00';
            $currency = $data['currency'] ?? 'USD';
            $paymode = config('app.env') === 'production' ? 'LIVE' : 'TEST_EXTERNAL';

            // Build request data exactly like working pay.php
            $requestData = "entityId=" . $authConfig['entityId'] .
                          "&amount=" . $amount .
                          "&currency=" . $currency .
                          "&paymentType=" . $authConfig['payType'];

            if ($paymode == "TEST_INTERNAL") {
                $requestData .= "&testMode=INTERNAL";
            } else if ($paymode == "TEST_EXTERNAL") {
                $requestData .= "&testMode=EXTERNAL";
            }

            // Make cURL request exactly like working implementation
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $authConfig['oppwaUrl']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($authConfig['authorizationBearer']));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $requestData);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $responseData = curl_exec($ch);

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \Exception('cURL Error: ' . $error);
            }

            curl_close($ch);

            // Parse response
            $decodedData = json_decode($responseData, true);

            // Debug logging
            Log::info('Zimswitch checkout creation response', [
                'raw_response' => $responseData,
                'decoded_response' => $decodedData,
                'request_data' => $requestData,
                'auth_config' => array_merge($authConfig, ['authorizationBearer' => '[HIDDEN]'])
            ]);

            if (!$decodedData || !isset($decodedData['id'])) {
                Log::error('Zimswitch checkout creation failed', [
                    'response' => $responseData,
                    'decoded' => $decodedData,
                    'request_data' => $requestData
                ]);
                throw new \Exception('Failed to create checkout: ' . ($decodedData['result']['description'] ?? 'Unknown error'));
            }

            // Return data for Vue.js integration
            return [
                'success' => true,
                'checkoutId' => $decodedData['id'],
                'trace' => $data['trace'] ?? (string) \Illuminate\Support\Str::uuid(),
                'paymentUrl' => $authConfig['baseUrl'] ?? $authConfig['checkoutUrl'],
                'authConfig' => [
                    'checkoutUrl' => $authConfig['checkoutUrl'],
                    'baseUrl' => $authConfig['baseUrl'],
                ],
                'integrateInVue' => true, // Flag to tell frontend to integrate in Vue.js
                'message' => 'Checkout created successfully',
                'amount' => $amount,
                'currency' => $currency,
                'paymode' => $paymode
            ];
        } catch (\Exception $e) {
            Log::error('Zimswitch payment error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => true,
                'message' => 'An error occurred while processing your payment: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check payment status using resource path
     * Uses cURL exactly like the working implementation
     *
     * @param string $resourcePath
     * @return array
     */
    public function checkPaymentStatus($resourcePath)
    {
        try {
            // Load auth configuration
            $authJsonPath = base_path('zimswitch/auth.json');
            if (!file_exists($authJsonPath)) {
                throw new \Exception('Authentication configuration file not found');
            }

            $authConfig = json_decode(file_get_contents($authJsonPath), true);
            if (!$authConfig) {
                throw new \Exception('Invalid authentication configuration');
            }

            // Build URL for payment status check exactly like working implementation
            $url = $authConfig['baseUrl'] . $resourcePath;
            $url .= "?entityId=" . $authConfig['entityId'];

            // Make cURL request exactly like working pay_result.php
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array($authConfig['authorizationBearer']));
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $responseData = curl_exec($ch);

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new \Exception('cURL Error: ' . $error);
            }

            curl_close($ch);

            // Parse response
            $decodedData = json_decode($responseData, true);

            if (!$decodedData) {
                throw new \Exception('Invalid response from payment gateway');
            }

            // Check payment status exactly like working implementation
            $resultCode = $decodedData['result']['code'] ?? '';
            $resultDescription = $decodedData['result']['description'] ?? '';

            // Success codes pattern from working implementation
            $successPattern = '/^(000\.000\.|000\.100\.1|000\.[36])/';
            $isSuccess = preg_match($successPattern, $resultCode);

            Log::info('Zimswitch payment status check', [
                'resourcePath' => $resourcePath,
                'resultCode' => $resultCode,
                'resultDescription' => $resultDescription,
                'isSuccess' => $isSuccess,
                'fullResponse' => $decodedData
            ]);

            return [
                'success' => (bool)$isSuccess,
                'resultCode' => $resultCode,
                'resultDescription' => $resultDescription,
                'amount' => $decodedData['amount'] ?? '',
                'currency' => $decodedData['currency'] ?? '',
                'transactionId' => $decodedData['id'] ?? '',
                'timestamp' => $decodedData['timestamp'] ?? '',
                'paymentBrand' => $decodedData['paymentBrand'] ?? '',
                'message' => $isSuccess ? 'Payment successful' : 'Payment failed: ' . $resultDescription
            ];

        } catch (\Exception $e) {
            Log::error('Zimswitch payment status check error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => true,
                'message' => 'An error occurred while checking payment status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get payment status by checkout ID
     *
     * @param string $checkoutId
     * @return array
     */
    public function getPaymentStatus($checkoutId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->authToken
            ])->get($this->baseUrl . '/v1/checkouts/' . $checkoutId . '/payment', [
                'entityId' => $this->entityId
            ]);

            $responseData = $response->json();

            if ($response->successful()) {
                $isSuccess = isset($responseData['result']['code']) &&
                            $responseData['result']['code'] === '000.100.110';

                return [
                    'success' => $isSuccess,
                    'error' => !$isSuccess,
                    'status' => $responseData['result']['description'] ?? 'Unknown',
                    'transactionId' => $responseData['id'] ?? null,
                    'responseCode' => $responseData['result']['code'] ?? '01',
                    'message' => $responseData['result']['description'] ?? 'Payment status unknown',
                    'responseData' => $responseData
                ];
            }

            return [
                'success' => false,
                'error' => true,
                'message' => 'Failed to get payment status',
                'responseData' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('Zimswitch payment status error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => true,
                'message' => 'An error occurred while checking payment status: ' . $e->getMessage()
            ];
        }
    }
}
