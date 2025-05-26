<?php

namespace App\Services;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OmariPaymentService
{
    protected $url;
    protected $merchantKey;

    public function __construct()
    {
        $this->url = config('services.omari.url');
        $this->merchantKey = config('services.omari.merchant_key');
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
        
        $user = User::where('email', $request['user'])->first();
        
        // Check if the user exists
        if (!$user) {
            throw new \Exception('User not found.');
        }

        // Check if user has a web service URL
        if ($user->role == 'MERCHANT') {
            $merchant = Merchant::where('user_id', $user->id)->first();
            if (!$merchant->web_service_url) {
                throw new \Exception('Configuration error: web_service_url is missing.');
            }
        } else {
            if (!$user->web_service_url) {
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

        // Send the auth request to Omari API
        $response = Http::withHeaders([
            'X-Merchant-Key' => $this->merchantKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/api/merchant/api/payment/auth", $payload);

        if (!$response->successful()) {
            throw new \Exception('Omari payment auth request failed: ' . $response->body());
        }

        $responseData = $response->json();
        
        // Add the reference to the response for future use
        $responseData['reference'] = $reference;
        $responseData['msisdn'] = $msisdn;
        
        return $responseData;
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

        $response = Http::withHeaders([
            'X-Merchant-Key' => $this->merchantKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->url}/api/merchant/api/payment/request", $payload);

        if (!$response->successful()) {
            throw new \Exception('Omari payment request failed: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Query payment status
     *
     * @param string $reference
     * @return array
     * @throws \Exception
     */
    public function inquirePaymentRequest($reference)
    {
        $response = Http::withHeaders([
            'X-Merchant-Key' => $this->merchantKey,
        ])->get("{$this->url}/api/merchant/api/payment/query/{$reference}");

        if (!$response->successful()) {
            throw new \Exception('Omari payment query failed: ' . $response->body());
        }

        return $response->json();
    }
}
