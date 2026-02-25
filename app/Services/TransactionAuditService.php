<?php

namespace App\Services;

use App\Models\TransactionAudit;
use Throwable;

class TransactionAuditService
{
    /**
     * Store an audit entry without interrupting payment flow on failures.
     */
    public function record(array $data): void
    {
        try {
            TransactionAudit::create([
                'transaction_id' => $data['transaction_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'trace' => $data['trace'] ?? null,
                'reference' => $data['reference'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'stage' => $data['stage'] ?? null,
                'event' => $data['event'] ?? 'UNKNOWN_EVENT',
                'level' => strtoupper($data['level'] ?? 'INFO'),
                'provider' => $data['provider'] ?? null,
                'endpoint' => $data['endpoint'] ?? null,
                'status_code' => $data['status_code'] ?? null,
                'request_payload' => $this->sanitize($data['request_payload'] ?? null),
                'response_payload' => $this->sanitize($data['response_payload'] ?? null),
                'meta_data' => $this->sanitize($data['meta_data'] ?? null),
            ]);
        } catch (Throwable $e) {
            // Avoid breaking core payment flow when audit write fails.
            report($e);
        }
    }

    /**
     * Attach pre-transaction audit rows that were captured by trace only.
     */
    public function linkToTransaction(string $trace, int $transactionId): void
    {
        TransactionAudit::query()
            ->where('trace', $trace)
            ->whereNull('transaction_id')
            ->update(['transaction_id' => $transactionId]);
    }

    private function sanitize($data)
    {
        if (is_array($data)) {
            $masked = [];

            foreach ($data as $key => $value) {
                if ($this->isSensitiveKey((string) $key)) {
                    $masked[$key] = '[REDACTED]';
                    continue;
                }

                $masked[$key] = $this->sanitize($value);
            }

            return $masked;
        }

        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'password')
            || str_contains($key, 'pin')
            || str_contains($key, 'cvv')
            || str_contains($key, 'otp')
            || str_contains($key, 'token')
            || str_contains($key, 'authorization')
            || str_contains($key, 'merchant_key')
            || str_contains($key, 'secret');
    }
}

