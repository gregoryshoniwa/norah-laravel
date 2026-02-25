<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class OmariPaymentService
{
    protected $url;
    protected $merchantKey;
    protected $transactionAuditService;

    public function __construct(TransactionAuditService $transactionAuditService)
    {
        $this->url = config('services.omari.url');
        $this->merchantKey = config('services.omari.merchant_key');
        $this->transactionAuditService = $transactionAuditService;
    }

    /**
     * Create an auth payment request and send OTP to the user
     *
     * @param array $request
     * @return array
     * @throws \Exception
     */
    public function createPaymentRequest(array $request)
    {
        $reference = Str::uuid()->toString();
        $trace = $request['_auditTrace'] ?? null;

        $user = User::where('email', $request['user'])->first();

        // Check if the user exists
        if (!$user) {
            $this->audit([
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'OMARI',
                'stage' => 'VALIDATION',
                'event' => 'USER_NOT_FOUND',
                'level' => 'ERROR',
                'provider' => 'OMARI',
                'request_payload' => $request,
            ]);
            throw new \Exception('User not found.');
        }

        // Check if user has a web service URL
        if ($user->role == 'MERCHANT') {
            $merchant = Merchant::where('user_id', $user->id)->first();
            if (!$merchant->web_service_url) {
                $this->audit([
                    'user_id' => $user->id,
                    'trace' => $trace,
                    'reference' => $reference,
                    'payment_method' => 'OMARI',
                    'stage' => 'VALIDATION',
                    'event' => 'MISSING_WEBHOOK_URL',
                    'level' => 'ERROR',
                    'provider' => 'OMARI',
                    'request_payload' => $request,
                ]);
                throw new \Exception('Configuration error: web_service_url is missing.');
            }
        } else {
            if (!$user->web_service_url) {
                $this->audit([
                    'user_id' => $user->id,
                    'trace' => $trace,
                    'reference' => $reference,
                    'payment_method' => 'OMARI',
                    'stage' => 'VALIDATION',
                    'event' => 'MISSING_WEBHOOK_URL',
                    'level' => 'ERROR',
                    'provider' => 'OMARI',
                    'request_payload' => $request,
                ]);
                throw new \Exception('Configuration error: web_service_url is missing.');
            }
        }

        // Format the phone number to ensure it has the correct format (263XXXXXXXXX)
        $msisdn = $request['phoneNumber'];
        if (!preg_match('/^263\d{9}$/', $msisdn)) {
            // If it doesn't start with 263, try to format it
            if (preg_match('/^0(\d{9})$/', $msisdn, $matches)) {
                $msisdn = '263' . $matches[1];
            } elseif (preg_match('/^\+263(\d{9})$/', $msisdn, $matches)) {
                $msisdn = '263' . $matches[1];
            }
        }

        // Build the request payload
        $payload = [
            'msisdn' => $msisdn,
            'reference' => $reference,
            'amount' => (float) $request['total'],
            'currency' => $request['currency'],
            'channel' => 'WEB'
        ];

        $endpoint = "{$this->url}/api/merchant/api/payment/auth";
        $this->audit([
            'user_id' => $user->id,
            'trace' => $trace,
            'reference' => $reference,
            'payment_method' => 'OMARI',
            'stage' => 'PROVIDER_REQUEST',
            'event' => 'OMARI_AUTH_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'OMARI',
            'endpoint' => $endpoint,
            'request_payload' => $payload,
        ]);

        try {
            $response = Http::withHeaders([
                'X-Merchant-Key' => $this->merchantKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            $this->audit([
                'user_id' => $user->id,
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'OMARI',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'OMARI_AUTH_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'OMARI',
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'request_payload' => $payload,
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
            ]);

            if (!$response->successful()) {
                throw new \Exception('Omari payment auth request failed: ' . $response->body());
            }

            $responseData = $response->json();

            // Add the reference to the response for future use
            $responseData['reference'] = $reference;
            $responseData['msisdn'] = $msisdn;

            return $responseData;
        } catch (Throwable $e) {
            $this->audit([
                'user_id' => $user->id,
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'OMARI',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'OMARI_AUTH_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'OMARI',
                'endpoint' => $endpoint,
                'request_payload' => $payload,
                'response_payload' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    /**
     * Process payment with OTP
     *
     * @param string $msisdn
     * @param string $reference
     * @param string $otp
     * @return array
     * @throws \Exception
     */
    public function processPayment($msisdn, $reference, $otp)
    {
        $payload = [
            'msisdn' => $msisdn,
            'reference' => $reference,
            'otp' => $otp
        ];

        $endpoint = "{$this->url}/api/merchant/api/payment/request";

        $this->audit([
            'trace' => $reference,
            'reference' => $reference,
            'payment_method' => 'OMARI',
            'stage' => 'PROVIDER_REQUEST',
            'event' => 'OMARI_PAYMENT_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'OMARI',
            'endpoint' => $endpoint,
            'request_payload' => $payload,
        ]);

        try {
            $response = Http::withHeaders([
                'X-Merchant-Key' => $this->merchantKey,
                'Content-Type' => 'application/json',
            ])->post($endpoint, $payload);

            $this->audit([
                'trace' => $reference,
                'reference' => $reference,
                'payment_method' => 'OMARI',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'OMARI_PAYMENT_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'OMARI',
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'request_payload' => $payload,
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
            ]);

            if (!$response->successful()) {
                throw new \Exception('Omari payment request failed: ' . $response->body());
            }

            return $response->json();
        } catch (Throwable $e) {
            $this->audit([
                'trace' => $reference,
                'reference' => $reference,
                'payment_method' => 'OMARI',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'OMARI_PAYMENT_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'OMARI',
                'endpoint' => $endpoint,
                'request_payload' => $payload,
                'response_payload' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    /**
     * Query payment status
     *
     * @param string $reference
     * @return array
     * @throws \Exception
     */
    public function inquirePaymentRequest($reference, ?string $trace = null)
    {
        $endpoint = "{$this->url}/api/merchant/api/payment/query/{$reference}";
        $auditTrace = $trace ?? $reference;

        $this->audit([
            'trace' => $auditTrace,
            'reference' => $reference,
            'payment_method' => 'OMARI',
            'stage' => 'PROVIDER_REQUEST',
            'event' => 'OMARI_QUERY_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'OMARI',
            'endpoint' => $endpoint,
        ]);

        try {
            $response = Http::withHeaders([
                'X-Merchant-Key' => $this->merchantKey,
            ])->get($endpoint);

            $this->audit([
                'trace' => $auditTrace,
                'reference' => $reference,
                'payment_method' => 'OMARI',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'OMARI_QUERY_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'OMARI',
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
            ]);

            if (!$response->successful()) {
                throw new \Exception('Omari payment query failed: ' . $response->body());
            }

            return $response->json();
        } catch (Throwable $e) {
            $this->audit([
                'trace' => $auditTrace,
                'reference' => $reference,
                'payment_method' => 'OMARI',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'OMARI_QUERY_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'OMARI',
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
