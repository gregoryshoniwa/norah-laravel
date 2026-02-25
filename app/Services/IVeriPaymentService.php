<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\TransactionAuditService;

class IVeriPaymentService
{
    protected $baseUrl;
    protected $applicationId;
    protected $certificateId;
    protected $userGroupNumber;
    protected $mode;
    protected $version;
    protected $transactionAuditService;

    public function __construct(TransactionAuditService $transactionAuditService)
    {
        $this->baseUrl = config('services.iveri.url');
        $this->applicationId = config('services.iveri.application_id');
        $this->certificateId = config('services.iveri.certificate_id');
        $this->userGroupNumber = config('services.iveri.user_group');
        $this->mode = config('services.iveri.mode', 'TEST');
        $this->version = config('services.iveri.version', '1.0');
        $this->transactionAuditService = $transactionAuditService;
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

        // Using the exact structure from the working cURL example
        $payload = [
            'Version' => '2.0',
            'CertificateID' => $this->certificateId,
            'ProductType' => 'Enterprise',
            'ProductVersion' => 'WebAPI',
            'Direction' => 'Request',
            'Transaction' => [
                'ApplicationID' => $this->applicationId,
                'Command' => 'Debit',
                'Mode' => $this->mode,
                'Amount' => $amount,
                'Currency' => $data['currency'] ?? 'USD',
                'MerchantReference' => $transactionId,
                'ApplicationMerchantCity' => 'Harare',
                'ApplicationMerchantCountryCode' => 'ZW',
                'ApplicationMerchantName' => 'Norah Payment Gateway',
                // Card details
                'PAN' => preg_replace('/\s+/', '', $data['cardNumber'] ?? ''),
                'ExpiryDate' => $this->formatExpiryDateForIveri($data['expiryDate'] ?? ''),
                'CardSecurityCode' => $data['cvv'] ?? '',
                // Standard 3D Secure parameters
                'ThreeDSecure_Required' => 'true',
                'ThreeDSecure_Enabled' => 'true',
                'ElectronicCommerceIndicator' => 'ThreeDSecure', // Required to avoid Code 255 error
                'ThreeDSecure_TermUrl' => url('/payment/callback?reference=' . $transactionId),
                'ThreeDSecure_AuthenticationType' => '01', // Fully authenticated transaction (01)
                'ThreeDSecure_ProtocolVersion' => '2.1.0',
                'CardHolderAuthenticationData' => 'AJkBCWhygQAAAAEDhXKBAAAAAAA=', // Required to avoid Code 255 error
                'CardHolderAuthenticationID' => 'xVyRZy0bYuN69j1pZi/zlmC68Vw=', // Matching authentication ID
            ]
        ];

        // // Add billing details if available
        // if (!empty($data['billing_address'])) {
        //     $payload['Transaction']['BillingAddress'] = $data['billing_address'] ?? '';
        //     $payload['Transaction']['BillingCity'] = $data['billing_city'] ?? '';
        //     $payload['Transaction']['BillingCountryCode'] = $data['billing_country'] ?? 'ZW';
        //     $payload['Transaction']['BillingPostalCode'] = $data['billing_zip'] ?? '';
        // }

        // // Add email if available
        // if (!empty($data['user'])) {
        //     $payload['Transaction']['CustomerEmailAddress'] = $data['user'];
        // }

        // Log request payload for debugging (mask sensitive data)
        Log::debug('iVeri payment request', [
            'url' => $this->baseUrl . '/api/transactions',
            'transactionId' => $transactionId,
            'amount' => $amount,
            'currency' => $data['currency'] ?? 'USD',
        ]);
        $this->audit([
            'trace' => $transactionId,
            'reference' => $transactionId,
            'payment_method' => 'VISA_MASTER',
            'stage' => 'PROVIDER_REQUEST',
            'event' => 'IVERI_PAYMENT_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'IVERI',
            'endpoint' => $this->baseUrl . '/api/transactions',
            'request_payload' => $payload,
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
            $this->audit([
                'trace' => $transactionId,
                'reference' => $transactionId,
                'payment_method' => 'VISA_MASTER',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'IVERI_PAYMENT_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'IVERI',
                'endpoint' => $this->baseUrl . '/api/transactions',
                'status_code' => $response->status(),
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
            ]);

            $responseData = $response->json();

            // Check if the response indicates a successful payment or redirect needed
            if ($response->successful()) {
                if (isset($responseData['Transaction'])) {
                    $transaction = $responseData['Transaction'];

                    // Check for 3D Secure URL for card authentication (direct redirect)
                    if (isset($transaction['ThreeDSecure_Url'])) {
                        return [
                            'success' => true,
                            'redirectUrl' => $transaction['ThreeDSecure_Url'],
                            'trace' => $transactionId,
                            'reference' => $transaction['TransactionIndex'] ?? null
                        ];
                    }

                    // Check for ACS Form data that needs to be posted to 3D Secure
                    if (isset($transaction['ThreeDSecure_ACSUrl']) && isset($transaction['ThreeDSecure_Payload'])) {
                        return [
                            'success' => true,
                            'acsUrl' => $transaction['ThreeDSecure_ACSUrl'],
                            'acsPayload' => $transaction['ThreeDSecure_Payload'],
                            'trace' => $transactionId,
                            'reference' => $transaction['TransactionIndex'] ?? null
                        ];
                    }

                    // Check if transaction has Result information (successful payment)
                    if (isset($transaction['Result'])) {
                        $result = $transaction['Result'];

                        // Check if transaction is approved (Status 0 indicates success)
                        if (isset($result['Status']) && $result['Status'] === '0') {
                            return [
                                'success' => true,
                                'message' => 'Payment approved',
                                'reference' => $transaction['TransactionIndex'] ?? null,
                                'trace' => $transactionId,
                                'responseData' => $transaction
                            ];
                        }
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
            $this->audit([
                'trace' => $transactionId,
                'reference' => $transactionId,
                'payment_method' => 'VISA_MASTER',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'IVERI_PAYMENT_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'IVERI',
                'endpoint' => $this->baseUrl . '/api/transactions',
                'response_payload' => ['message' => $e->getMessage()],
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
            $this->audit([
                'trace' => $transactionIndex,
                'reference' => $transactionIndex,
                'payment_method' => 'VISA_MASTER',
                'stage' => 'PROVIDER_REQUEST',
                'event' => 'IVERI_STATUS_REQUEST_SENT',
                'level' => 'INFO',
                'provider' => 'IVERI',
                'endpoint' => $this->baseUrl . '/api/transactions',
                'request_payload' => $payload,
            ]);

            // Log the response for debugging
            Log::debug('iVeri payment status check', [
                'transactionIndex' => $transactionIndex,
                'status' => $response->status(),
                'body' => $response->json()
            ]);
            $this->audit([
                'trace' => $transactionIndex,
                'reference' => $transactionIndex,
                'payment_method' => 'VISA_MASTER',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'IVERI_STATUS_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'IVERI',
                'endpoint' => $this->baseUrl . '/api/transactions',
                'status_code' => $response->status(),
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
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
            $this->audit([
                'trace' => $transactionIndex,
                'reference' => $transactionIndex,
                'payment_method' => 'VISA_MASTER',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'IVERI_STATUS_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'IVERI',
                'endpoint' => $this->baseUrl . '/api/transactions',
                'response_payload' => ['message' => $e->getMessage()],
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

    /**
     * Initiate 3D Secure enrollment process
     *
     * @param array $data
     * @return array
     */
    public function initiate3DSecureEnrollment($data)
    {
        // Extract transaction data from the request
        $transactionId = $data['trace'] ?? (string) Str::uuid();
        $transactionIndex = $data['transactionIndex'] ?? null;

        // Prepare the 3D Secure Enrollment request
        $payload = [
            'Version' => '2.0',
            'CertificateID' => $this->certificateId,
            'ProductType' => 'Enterprise',
            'ProductVersion' => 'WebAPI',
            'Direction' => 'Request',
            'Transaction' => [
                'ApplicationID' => $this->applicationId,
                'Command' => 'ThreeDSecure',
                'Mode' => $this->mode,
                'Function' => 'EnrollmentInitial',
                'TransactionIndex' => $transactionIndex, // Required for 3DS enrollment
                'ThreeDSecure_ProtocolVersion' => '2.1.0',
                'ThreeDSecure_RedirectUrl' => route('payment.callback') . '?reference=' . $transactionId,
                'ThreeDSecure_TermUrl' => route('payment.callback') . '?reference=' . $transactionId,
                'ApplicationMerchantName' => 'Norah Payment Gateway',
                'ApplicationMerchantCity' => 'Harare',
                'ApplicationMerchantCountryCode' => 'ZW'
            ]
        ];

        // Log request payload for debugging (mask sensitive data)
        Log::debug('iVeri 3DS enrollment request', [
            'payload' => json_encode($payload),
            'url' => $this->baseUrl . '/api/transactions'
        ]);
        $this->audit([
            'trace' => $transactionId,
            'reference' => $transactionIndex,
            'payment_method' => 'VISA_MASTER',
            'stage' => 'PROVIDER_REQUEST',
            'event' => 'IVERI_3DS_ENROLLMENT_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'IVERI',
            'endpoint' => $this->baseUrl . '/api/transactions',
            'request_payload' => $payload,
        ]);

        try {
            // Make the API request to iVeri
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/transactions', $payload);

            // Parse the response body
            $responseData = $response->json();

            // Log the response for debugging
            Log::debug('iVeri 3DS enrollment response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
            $this->audit([
                'trace' => $transactionId,
                'reference' => $transactionIndex,
                'payment_method' => 'VISA_MASTER',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'IVERI_3DS_ENROLLMENT_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'IVERI',
                'endpoint' => $this->baseUrl . '/api/transactions',
                'status_code' => $response->status(),
                'response_payload' => $responseData,
            ]);

            if ($response->successful()) {
                if (isset($responseData['Transaction'])) {
                    $transaction = $responseData['Transaction'];

                    // Check for 3D Secure URL for authentication challenge
                    if (isset($transaction['ThreeDSecure_Url'])) {
                        return [
                            'success' => true,
                            'redirectUrl' => $transaction['ThreeDSecure_Url'],
                            'reference' => $transaction['TransactionIndex'] ?? $transactionIndex,
                            'trace' => $transactionId
                        ];
                    }

                    // Check if we got a successful 3DS enrollment response
                    if (isset($transaction['ThreeDSecure_AuthenticationValue'])) {
                        return [
                            'success' => true,
                            'threeDSecureComplete' => true,
                            'authValue' => $transaction['ThreeDSecure_AuthenticationValue'],
                            'reference' => $transaction['TransactionIndex'] ?? $transactionIndex,
                            'trace' => $transactionId,
                            'responseData' => $transaction
                        ];
                    }

                    // Handle any result codes or errors
                    if (isset($transaction['Result'])) {
                        $result = $transaction['Result'];

                        return [
                            'success' => false,
                            'error' => true,
                            'message' => $result['Description'] ?? 'Error during 3D Secure enrollment',
                            'code' => $result['Code'] ?? 'unknown',
                            'trace' => $transactionId,
                            'responseData' => $transaction
                        ];
                    }
                }
            }

            // Handle failed API responses
            return [
                'success' => false,
                'error' => true,
                'message' => $responseData['message'] ?? 'Failed to process 3D Secure enrollment',
                'trace' => $transactionId,
                'responseData' => $responseData
            ];

        } catch (\Exception $e) {
            // Log the exception
            Log::error('iVeri 3DS enrollment exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->audit([
                'trace' => $transactionId,
                'reference' => $transactionIndex,
                'payment_method' => 'VISA_MASTER',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'IVERI_3DS_ENROLLMENT_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'IVERI',
                'endpoint' => $this->baseUrl . '/api/transactions',
                'response_payload' => ['message' => $e->getMessage()],
            ]);

            // Return error response
            return [
                'success' => false,
                'error' => true,
                'message' => 'Error connecting to iVeri: ' . $e->getMessage(),
                'trace' => $transactionId
            ];
        }
    }

    private function audit(array $data): void
    {
        $this->transactionAuditService->record($data);
    }
}
