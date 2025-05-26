<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IVeriPaymentService
{
    protected $baseUrl;
    protected $applicationId;
    protected $certificateId;
    protected $userGroupNumber;
    protected $mode;
    protected $version;

    public function __construct()
    {
        $this->baseUrl = config('services.iveri.url');
        $this->applicationId = config('services.iveri.application_id');
        $this->certificateId = config('services.iveri.certificate_id');
        $this->userGroupNumber = config('services.iveri.user_group');
        $this->mode = config('services.iveri.mode', 'TEST');
        $this->version = config('services.iveri.version', '1.0');
    }

    /**
     * Process a card payment through iVeri Enterprise API
     *
     * @param array $data
     * @return array
     */
    public function processPayment($data)
    {
        // Generate a unique transaction ID if not provided
        $transactionId = $data['trace'] ?? (string) Str::uuid();

        // Format amount as required by iVeri (No decimal point, padded with zeros)
        $amount = number_format($data['amount'] * 100, 0, '', '');

        // Use iVeri Redirect - hosted payment page approach (similar to Zimswitch)
        $payload = [
            'Version' => '2.0',
            'CertificateID' => $this->certificateId,
            'ProductType' => 'Enterprise',
            'ProductVersion' => 'WebAPI',
            'Direction' => 'Request',
            'Interface' => 'Redirect', // Set the interface to Redirect for hosted payment page
            'Transaction' => [
                'ApplicationID' => $this->applicationId,
                'Command' => 'Debit',
                'Mode' => $this->mode,
                'Amount' => $amount,
                'Currency' => $data['currency'] ?? 'USD',
                'MerchantReference' => $transactionId,
                // Don't include card details since they'll be collected on the hosted form
                // Add callback URLs
                'RedirectSuccessUrl' => route('payment.callback') . '?status=success&reference=' . $transactionId,
                'RedirectFailureUrl' => route('payment.callback') . '?status=failure&reference=' . $transactionId,
                'RedirectCancelUrl' => route('payment.callback') . '?status=cancel&reference=' . $transactionId,
                // Add merchant details that were missing in previous implementation
                'MerchantCity' => 'Harare', // Required field that was missing before
                'MerchantCountryCode' => 'ZW'
            ]
        ];
        
        // Add billing details if available
        if (!empty($data['billing_address'])) {
            $payload['Transaction']['BillingAddress'] = $data['billing_address'] ?? '';
            $payload['Transaction']['BillingCity'] = $data['billing_city'] ?? '';
            $payload['Transaction']['BillingCountryCode'] = $data['billing_country'] ?? 'ZW';
            $payload['Transaction']['BillingPostalCode'] = $data['billing_zip'] ?? '';
        }
        
        // Add email if available
        if (!empty($data['user'])) {
            $payload['Transaction']['CustomerEmailAddress'] = $data['user'];
        }

        // Log request payload for debugging (mask sensitive data)
        Log::debug('iVeri payment request', [
            'url' => $this->baseUrl . '/api/transactions',
            'transactionId' => $transactionId,
            'amount' => $amount,
            'currency' => $data['currency'] ?? 'USD',
        ]);

        try {
            // Make the API request to the transactions endpoint as used in Java app
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/api/transactions', $payload);

            // Log the raw response for debugging
            Log::debug('iVeri payment response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            $responseData = $response->json();

            // Check if the response indicates a successful payment or redirect needed
            if ($response->successful()) {
                // For Redirect interface, we're looking for a RedirectUrl to the hosted payment page
                if (isset($responseData['Transaction'])) {
                    $transaction = $responseData['Transaction'];
                    
                    // Check if we have a redirect URL to the hosted payment page
                    if (isset($transaction['RedirectUrl'])) {
                        return [
                            'success' => true,
                            'paymentUrl' => $this->baseUrl, // Base URL for constructing the final payment page URL
                            'redirectUrl' => $transaction['RedirectUrl'], // URL to redirect to for payment
                            'checkoutId' => $transaction['TransactionIndex'] ?? $transactionId, // Used similar to Zimswitch's checkoutId
                            'trace' => $transactionId
                        ];
                    }
                    
                    // Check if transaction has Result information
                    if (isset($transaction['Result'])) {
                        $result = $transaction['Result'];
                        
                        // Check if transaction is approved (Status 0 indicates success)
                        if (isset($result['Status']) && $result['Status'] === '0') {
                            return [
                                'success' => true,
                                'message' => 'Payment approved',
                                'reference' => $transaction['TransactionIndex'] ?? $transactionId,
                                'trace' => $transactionId,
                                'responseData' => $transaction
                            ];
                        }
                        
                        // If we have a result but it's not successful, return the error details
                        return [
                            'success' => false,
                            'error' => true,
                            'message' => $result['Description'] ?? 'Payment processing failed',
                            'code' => $result['Code'] ?? 'unknown',
                            'trace' => $transactionId,
                            'responseData' => $transaction
                        ];
                    }
                    
                    // Check for pending status
                    if (isset($transaction['Status']) && $transaction['Status'] === 'Pending') {
                        return [
                            'success' => true,
                            'pending' => true,
                            'message' => 'Payment is pending verification',
                            'reference' => $transaction['TransactionIndex'] ?? $transactionId,
                            'trace' => $transactionId
                        ];
                    }
                }
            }

            // If we got here, something went wrong with no transaction data
            return [
                'success' => false,
                'error' => true,
                'message' => $responseData['Message'] ?? 'Payment processing failed',
                'trace' => $transactionId
            ];
        } catch (\Exception $e) {
            // Log any exceptions
            Log::error('iVeri payment error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => true,
                'message' => 'Payment system error: ' . $e->getMessage(),
                'trace' => $transactionId
            ];
        }
    }

    /**
     * Check the status of a payment using the transaction index
     *
     * @param string $transactionIndex
     * @return array
     */
    public function checkPaymentStatus($transactionIndex)
    {
        try {
            // Prepare the payload for status check based on working example
            $payload = [
                'Version' => '2.0',
                'CertificateID' => $this->certificateId,
                'ProductType' => 'Enterprise',
                'ProductVersion' => 'WebAPI',
                'Direction' => 'Request',
                'Transaction' => [
                    'ApplicationID' => $this->applicationId,
                    'Command' => 'Query',
                    'Mode' => $this->mode,
                    'TransactionIndex' => $transactionIndex
                ]
            ];

            // Make the API request to check payment status
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->baseUrl . '/api/transactions', $payload);

            // Log the response for debugging
            Log::debug('iVeri payment status check', [
                'transactionIndex' => $transactionIndex,
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            $responseData = $response->json();

            if ($response->successful()) {
                // Based on sample response format
                if (isset($responseData['Transaction'])) {
                    $transaction = $responseData['Transaction'];
                    
                    // Check if transaction has Result information
                    if (isset($transaction['Result'])) {
                        $result = $transaction['Result'];
                        
                        // Status 0 indicates success
                        $isSuccess = $result['Status'] === '0';
                        $isFailed = $result['Status'] !== '0';
                        
                        return [
                            'success' => $isSuccess,
                            'error' => $isFailed,
                            'pending' => isset($transaction['Status']) && $transaction['Status'] === 'Pending',
                            'message' => $result['Description'] ?? ($isSuccess ? 'Payment approved' : 'Payment failed'),
                            'data' => $transaction,
                            'cardType' => $transaction['CardType'] ?? null,
                            'association' => $transaction['Association'] ?? null,
                            'displayAmount' => $transaction['DisplayAmount'] ?? null
                        ];
                    }
                }
            }

            return [
                'success' => false,
                'error' => true,
                'message' => $responseData['Message'] ?? 'Could not determine payment status',
                'data' => $responseData
            ];
        } catch (\Exception $e) {
            // Log any exceptions
            Log::error('iVeri payment status check error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => true,
                'message' => 'Error checking payment status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Format expiry date from MM/YY format to MMYY for iVeri
     */
    private function formatExpiryDateForIveri($expiryDate)
    {
        // Format is typically MM/YY but iVeri expects MMYY
        // Remove any non-digit characters and spaces
        $cleaned = preg_replace('/[^0-9]/', '', $expiryDate);
        
        // Make sure we have at least 4 digits (MMYY)
        if (strlen($cleaned) >= 4) {
            return substr($cleaned, 0, 4); // Take only first 4 digits (MMYY)
        }
        
        // Return empty if format is invalid
        return '';
    }
}
