<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class InnBucksPaymentService
{
    protected $url;
    protected $username;
    protected $password;
    protected $key;

    public function __construct()
    {
        $this->url = config('services.innbucks.url');
        $this->username = config('services.innbucks.username');
        $this->password = config('services.innbucks.password');
        $this->key = config('services.innbucks.key');
    }

    public function login()
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->key,
        ])->post("{$this->url}/auth/third-party", [
            'username' => $this->username,
            'password' => $this->password,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('InnBucks login failed: ' . $response->body());
    }

    public function createPaymentRequest(array $request)
    {
        // Convert the amount to cents (integer)
        if (isset($request['total'])) {
            $request['total'] = (int) round($request['total'] * 100);
            $request['amount'] = $request['total'];
        }
        $request['reference'] = Str::uuid()->toString();

        $loginResponse = $this->login();

        if (!isset($loginResponse['accessToken'])) {
            throw new \Exception('InnBucks login failed: No access token received.');
        }

        $token = $loginResponse['accessToken'];

        $response = Http::withHeaders([
            'x-api-key' => $this->key,
            'Authorization' => "Bearer {$token}",
        ])->post("{$this->url}/api/code/generate", $request);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('InnBucks payment request failed: ' . $response->body());
    }

    public function inquirePaymentRequest(array $request)
    {
        $loginResponse = $this->login();

        if (!isset($loginResponse['accessToken'])) {
            throw new \Exception('InnBucks login failed: No access token received.');
        }

        $token = $loginResponse['accessToken'];

        $response = Http::withHeaders([
            'x-api-key' => $this->key,
            'Authorization' => "Bearer {$token}",
        ])->post("{$this->url}/api/code/inquiry", $request);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('InnBucks inquiry request failed: ' . $response->body());
    }
}
