<?php

namespace App\Services;

use App\Models\Merchant;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Throwable;

class EcoCashPaymentService
{
    protected $url;
    protected $username;
    protected $password;
    protected $transactionAuditService;

    public function __construct(TransactionAuditService $transactionAuditService)
    {
        $this->url = config('services.ecocash.url');
        $this->username = config('services.ecocash.username');
        $this->password = config('services.ecocash.password');
        $this->transactionAuditService = $transactionAuditService;
    }

    public function createPaymentRequest(array $request)
    {
        $auth = base64_encode("{$this->username}:{$this->password}");
        $reference = "NPG_" . time();
        $trace = $request['_auditTrace'] ?? null;

        $user = User::where('email', $request['user'])->first();
         // Check if the user exists
         if (!$user) {
            $this->audit([
                'user_id' => null,
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'ECOCASH',
                'stage' => 'VALIDATION',
                'event' => 'USER_NOT_FOUND',
                'level' => 'ERROR',
                'provider' => 'ECOCASH',
                'request_payload' => $request,
                'response_payload' => ['message' => 'User not found.'],
            ]);
            throw new \Exception('User not found.');
        }

        //if user is a merchant
        if ($user->role == 'MERCHANT') {
            $merchant = Merchant::where('user_id', $user->id)->first();
                // Check if the user has a web service URL
                if (!$merchant->web_service_url) {
                    $this->audit([
                        'user_id' => $user->id,
                        'trace' => $trace,
                        'reference' => $reference,
                        'payment_method' => 'ECOCASH',
                        'stage' => 'VALIDATION',
                        'event' => 'MISSING_WEBHOOK_URL',
                        'level' => 'ERROR',
                        'provider' => 'ECOCASH',
                        'request_payload' => $request,
                    ]);
                    throw new \Exception('Configuration error : web_service_url is missing.');
                }
        }else{
            // Check if the user has a web service URL
            if (!$user->web_service_url) {
                $this->audit([
                    'user_id' => $user->id,
                    'trace' => $trace,
                    'reference' => $reference,
                    'payment_method' => 'ECOCASH',
                    'stage' => 'VALIDATION',
                    'event' => 'MISSING_WEBHOOK_URL',
                    'level' => 'ERROR',
                    'provider' => 'ECOCASH',
                    'request_payload' => $request,
                ]);
                throw new \Exception('Configuration error : web_service_url is missing.');
            }
        }


        // Transform the incoming request to match the API's required format
        $apiRequest = [
            "clientCorrelator" => $reference, // Generate a random unique correlator
            "notifyUrl" => $user->web_service_url, // Use notifyUrl from request or default to app URL
            "referenceCode" => $reference, // Generate a random reference code
            "tranType" => "MER",
            "endUserId" => $request['phoneNumber'], // Map phoneNumber from the frontend request
            "remarks" => "Norah Payments",
            "transactionOperationStatus" => "Charged",
            "paymentAmount" => [
                "charginginformation" => [
                    "amount" => $request['total'], // Map amount from the frontend request
                    "currency" => $request['currency'], // Map currency from the frontend request
                    "description" => "Norah Online Payment"
                ],
                "chargeMetaData" => [
                    "channel" => "WEB",
                    "purchaseCategoryCode" => "Online Payment",
                    "onBeHalfOf" => "Norah pgw"
                ]
            ],
            "merchantCode" => env('ECOCASH_MERCHANT_CODE'),
            "merchantPin" => env('ECOCASH_MERCHANT_PIN'),
            "merchantNumber" => env('ECOCASH_MERCHANT_NUMBER'),
            "currencyCode" =>  $request['currency'], // Map currency from the frontend request
            "countryCode" => "ZW",
            "terminalID" => "NORAH1",
            "location" => "194 Baines Avenue, Harare, Zimbabwe",
            "superMerchantName" => "NOARH",
            "merchantName" => "Noarh Payment Gateway"
        ];

        $endpoint = "{$this->url}/transactions/amount";
        $this->audit([
            'transaction_id' => null,
            'user_id' => $user->id,
            'trace' => $trace,
            'reference' => $reference,
            'payment_method' => 'ECOCASH',
            'stage' => 'PROVIDER_REQUEST',
            'event' => 'ECOCASH_CREATE_PAYMENT_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'ECOCASH',
            'endpoint' => $endpoint,
            'request_payload' => $apiRequest,
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$auth}",
                'Content-Type' => 'application/json',
            ])->post($endpoint, $apiRequest);

            $this->audit([
                'transaction_id' => null,
                'user_id' => $user->id,
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'ECOCASH',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'ECOCASH_CREATE_PAYMENT_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'ECOCASH',
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'request_payload' => $apiRequest,
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('EcoCash payment request failed: ' . $response->body());
        } catch (Throwable $e) {
            $this->audit([
                'transaction_id' => null,
                'user_id' => $user->id,
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'ECOCASH',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'ECOCASH_CREATE_PAYMENT_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'ECOCASH',
                'endpoint' => $endpoint,
                'request_payload' => $apiRequest,
                'response_payload' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    public function inquirePaymentRequest(string $phoneNumber, string $clientCorrelator, ?string $trace = null)
    {
        $auth = base64_encode("{$this->username}:{$this->password}");
        $endpoint = "{$this->url}/{$phoneNumber}/transactions/amount/{$clientCorrelator}";
        $auditTrace = $trace ?? $clientCorrelator;

        $this->audit([
            'trace' => $auditTrace,
            'reference' => $clientCorrelator,
            'payment_method' => 'ECOCASH',
            'stage' => 'PROVIDER_REQUEST',
            'event' => 'ECOCASH_INQUIRY_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'ECOCASH',
            'endpoint' => $endpoint,
            'request_payload' => [
                'phoneNumber' => $phoneNumber,
                'clientCorrelator' => $clientCorrelator,
            ],
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Basic {$auth}",
            ])->get($endpoint);

            $this->audit([
                'trace' => $auditTrace,
                'reference' => $clientCorrelator,
                'payment_method' => 'ECOCASH',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'ECOCASH_INQUIRY_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'ECOCASH',
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'request_payload' => [
                    'phoneNumber' => $phoneNumber,
                    'clientCorrelator' => $clientCorrelator,
                ],
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('EcoCash inquiry request failed: ' . $response->body());
        } catch (Throwable $e) {
            $this->audit([
                'trace' => $auditTrace,
                'reference' => $clientCorrelator,
                'payment_method' => 'ECOCASH',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'ECOCASH_INQUIRY_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'ECOCASH',
                'endpoint' => $endpoint,
                'response_payload' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    private function audit(array $data): void
    {
        $this->transactionAuditService->record($data);
    }
}
