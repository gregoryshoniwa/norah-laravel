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
     *
     * @param array $data
     * @return array
     */
    public function prepareCheckout($data)
    {
        try {
            // Prepare request payload combining working cURL example with required documentation parameters
            $payload = [
                'entityId' => $this->entityId,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD', // Get currency from data or default to USD
                'paymentType' => 'DB', // Direct Debit
                // Add integrity flag as shown in the working cURL command
                'integrity' => 'true'
            ];

            // // Add transaction ID (required for transaction tracking)
            // $payload['merchantTransactionId'] = $data['trace'] ?? (string) \Illuminate\Support\Str::uuid();

            // // Add customer email (important for receipts and notifications)
            // $payload['customer.email'] = $data['user'] ?? '';

            // // Add billing information (required according to documentation)
            // $payload['billing.street1'] = $data['billing_address'] ?? 'N/A';
            // $payload['billing.city'] = $data['billing_city'] ?? 'N/A';
            // $payload['billing.state'] = $data['billing_state'] ?? 'N/A';
            // $payload['billing.country'] = 'ZW'; // Zimbabwe
            // $payload['billing.postcode'] = $data['billing_zip'] ?? 'N/A';

            // Add payment brand (specific to Zimswitch/Private Label)
            if (!empty($this->paymentBrand)) {
                $payload['customParameters[SHOPPER_payment_brand]'] = $this->paymentBrand;
            }

            // Add shopper result URL for redirect after payment (required)
            $payload['shopperResultUrl'] = route('payment.callback');

            // Log request payload for debugging
            \Illuminate\Support\Facades\Log::debug('Zimswitch payment request', [
                'url' => $this->baseUrl . '/v1/checkouts',
                'payload' => $payload
            ]);

            // Make the API request with detailed error handling
            try {
                // Convert payload to URL-encoded format (matching exactly how cURL sends it)
                $encodedPayload = http_build_query($payload);

                // Use the withBody method to ensure proper encoding
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->authToken,
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ])->withBody(
                    $encodedPayload, 'application/x-www-form-urlencoded'
                )->post($this->baseUrl . '/v1/checkouts');

                // Log the raw response for debugging
                \Illuminate\Support\Facades\Log::debug('Zimswitch payment response', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);

                $responseData = $response->json();

                // Check if the response contains a checkout ID (success case)
                if (isset($responseData['id'])) {
                    return [
                        'success' => true,
                        'checkoutId' => $responseData['id'],
                        'trace' => $data['trace']
                    ];
                }

                // If we got a response but no checkout ID, log it and return error
                \Illuminate\Support\Facades\Log::error('Zimswitch payment error - No checkout ID', [
                    'responseData' => $responseData
                ]);

                return [
                    'success' => false,
                    'error' => true,
                    'message' => $responseData['result']['description'] ?? 'Failed to prepare checkout',
                    'responseData' => $responseData
                ];
            } catch (\Exception $e) {
                // Log detailed exception information
                \Illuminate\Support\Facades\Log::error('Zimswitch payment exception', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);

                return [
                    'success' => false,
                    'error' => true,
                    'message' => 'Exception during checkout preparation: ' . $e->getMessage(),
                    'exception' => true
                ];
            }
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
