<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class InnBucksPaymentService
{
    protected $url;
    protected $username;
    protected $password;
    protected $key;
    protected $transactionAuditService;

    public function __construct(TransactionAuditService $transactionAuditService)
    {
        $this->url = config('services.innbucks.url');
        $this->username = config('services.innbucks.username');
        $this->password = config('services.innbucks.password');
        $this->key = config('services.innbucks.key');
        $this->transactionAuditService = $transactionAuditService;
    }

    public function login(?string $trace = null, ?string $reference = null)
    {
        $endpoint = "{$this->url}/auth/third-party";
        $payload = [
            'username' => $this->username,
            'password' => $this->password,
        ];

        $this->audit([
            'trace' => $trace,
            'reference' => $reference,
            'payment_method' => 'INNBUCKS',
            'stage' => 'PROVIDER_AUTH',
            'event' => 'INNBUCKS_LOGIN_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'INNBUCKS',
            'endpoint' => $endpoint,
            'request_payload' => $payload,
        ]);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->key,
            ])->post($endpoint, $payload);

            $this->audit([
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'INNBUCKS',
                'stage' => 'PROVIDER_AUTH',
                'event' => 'INNBUCKS_LOGIN_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'INNBUCKS',
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('InnBucks login failed: ' . $response->body());
        } catch (Throwable $e) {
            $this->audit([
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'INNBUCKS',
                'stage' => 'PROVIDER_AUTH',
                'event' => 'INNBUCKS_LOGIN_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'INNBUCKS',
                'endpoint' => $endpoint,
                'response_payload' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    public function createPaymentRequest(array $request)
    {
        $trace = $request['_auditTrace'] ?? $request['trace'] ?? null;
        // Convert the amount to cents (integer)
        if (isset($request['total'])) {
            $request['total'] = (int) round($request['total'] * 100);
            $request['amount'] = $request['total'];
        }
        $request['reference'] = Str::uuid()->toString();
        $reference = $request['reference'];

        $loginResponse = $this->login($trace, $reference);

        if (!isset($loginResponse['accessToken'])) {
            $this->audit([
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'INNBUCKS',
                'stage' => 'VALIDATION',
                'event' => 'INNBUCKS_LOGIN_TOKEN_MISSING',
                'level' => 'ERROR',
                'provider' => 'INNBUCKS',
            ]);
            throw new \Exception('InnBucks login failed: No access token received.');
        }

        $token = $loginResponse['accessToken'];
        $endpoint = "{$this->url}/api/code/generate";

        $this->audit([
            'trace' => $trace,
            'reference' => $reference,
            'payment_method' => 'INNBUCKS',
            'stage' => 'PROVIDER_REQUEST',
            'event' => 'INNBUCKS_PAYMENT_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'INNBUCKS',
            'endpoint' => $endpoint,
            'request_payload' => $request,
        ]);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->key,
                'Authorization' => "Bearer {$token}",
            ])->post($endpoint, $request);

            $this->audit([
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'INNBUCKS',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'INNBUCKS_PAYMENT_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'INNBUCKS',
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('InnBucks payment request failed: ' . $response->body());
        } catch (Throwable $e) {
            $this->audit([
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'INNBUCKS',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'INNBUCKS_PAYMENT_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'INNBUCKS',
                'endpoint' => $endpoint,
                'response_payload' => ['message' => $e->getMessage()],
            ]);

            throw $e;
        }
    }

    public function inquirePaymentRequest(array $request)
    {
        $trace = $request['reference'] ?? null;
        $reference = $request['code'] ?? null;
        $loginResponse = $this->login($trace, $reference);

        if (!isset($loginResponse['accessToken'])) {
            $this->audit([
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'INNBUCKS',
                'stage' => 'VALIDATION',
                'event' => 'INNBUCKS_INQUIRY_LOGIN_TOKEN_MISSING',
                'level' => 'ERROR',
                'provider' => 'INNBUCKS',
            ]);
            throw new \Exception('InnBucks login failed: No access token received.');
        }

        $token = $loginResponse['accessToken'];
        $endpoint = "{$this->url}/api/code/inquiry";

        $this->audit([
            'trace' => $trace,
            'reference' => $reference,
            'payment_method' => 'INNBUCKS',
            'stage' => 'PROVIDER_REQUEST',
            'event' => 'INNBUCKS_INQUIRY_REQUEST_SENT',
            'level' => 'INFO',
            'provider' => 'INNBUCKS',
            'endpoint' => $endpoint,
            'request_payload' => $request,
        ]);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->key,
                'Authorization' => "Bearer {$token}",
            ])->post($endpoint, $request);

            $this->audit([
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'INNBUCKS',
                'stage' => 'PROVIDER_RESPONSE',
                'event' => 'INNBUCKS_INQUIRY_RESPONSE_RECEIVED',
                'level' => $response->successful() ? 'INFO' : 'ERROR',
                'provider' => 'INNBUCKS',
                'endpoint' => $endpoint,
                'status_code' => $response->status(),
                'response_payload' => $response->json() ?? ['raw' => $response->body()],
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            throw new \Exception('InnBucks inquiry request failed: ' . $response->body());
        } catch (Throwable $e) {
            $this->audit([
                'trace' => $trace,
                'reference' => $reference,
                'payment_method' => 'INNBUCKS',
                'stage' => 'PROVIDER_EXCEPTION',
                'event' => 'INNBUCKS_INQUIRY_EXCEPTION',
                'level' => 'ERROR',
                'provider' => 'INNBUCKS',
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
