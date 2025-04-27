<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\User;

class EcoCashPaymentService
{
    protected $url;
    protected $username;
    protected $password;

    public function __construct()
    {
        $this->url = config('services.ecocash.url');
        $this->username = config('services.ecocash.username');
        $this->password = config('services.ecocash.password');
    }

    public function createPaymentRequest(array $request)
    {
        $auth = base64_encode("{$this->username}:{$this->password}");
        $reference = time();

        $user = User::where('email', $request['user'])->first();

        if (!$user || !$user->web_service_url) {
            throw new \Exception('User not found or web_service_url is missing.');
        }

        // Transform the incoming request to match the API's required format
        $apiRequest = [
            "clientCorrelator" => $reference, // Generate a random unique correlator
            "notifyUrl" => $user->web_service_url, // Use notifyUrl from request or default to app URL
            "referenceCode" => $reference, // Generate a random reference code
            "tranType" => "MER",
            "endUserId" => $request['phoneNumber'], // Map phoneNumber from the frontend request
            "remarks" => "Norah Payment",
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
            "merchantCode" => "001535",
            "merchantPin" => "4827",
            "merchantNumber" => "788732685",
            "currencyCode" =>  $request['currency'], // Map currency from the frontend request
            "countryCode" => "ZW",
            "terminalID" => "NORAH1",
            "location" => "194 Baines Avenue, Harare, Zimbabwe",
            "superMerchantName" => "NOARH",
            "merchantName" => "Noarh Payment Gateway"
        ];

         // Send the transformed request to the EcoCash API
        $response = Http::withHeaders([
            'Authorization' => "Basic {$auth}",
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/transactions/amount", $apiRequest);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('EcoCash payment request failed: ' . $response->body());
    }

    public function inquirePaymentRequest(string $phoneNumber, string $clientCorrelator)
    {
        $auth = base64_encode("{$this->username}:{$this->password}");

        $response = Http::withHeaders([
            'Authorization' => "Basic {$auth}",
        ])->get("{$this->url}/{$phoneNumber}/transactions/amount/{$clientCorrelator}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('EcoCash inquiry request failed: ' . $response->body());
    }
}
