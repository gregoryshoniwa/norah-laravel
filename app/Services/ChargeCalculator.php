<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\SystemCharge;
use App\Models\User;
use RuntimeException;

/**
 * Single source of truth for quoting the charges applied to a payment.
 *
 * Mirrors the lookup used by the checkout-token generators:
 *  - ADMIN ("U") accounts pay the system charge configured against their own email.
 *  - MERCHANT ("M") accounts pay the system charge configured against their
 *    parent (primary_user) email, plus their own MERCHANT-sourced charge.
 */
class ChargeCalculator
{
    /**
     * @return array{
     *   system_charge: float,
     *   merchant_charge: float,
     *   total_charge: float,
     *   total_amount: float,
     *   user_type: string
     * }
     *
     * @throws RuntimeException when no applicable system charge exists.
     */
    public function quote(User $user, float $amount, string $currency): array
    {
        $userType = $user->role === 'ADMIN' ? 'U' : 'M';

        if ($userType === 'U') {
            $systemChargeEmail = $user->email;
        } else {
            $parent = User::find($user->primary_user);
            if (!$parent) {
                throw new RuntimeException('Parent account not found for merchant.');
            }
            $systemChargeEmail = $parent->email;
        }

        $systemCharge = SystemCharge::active()
            ->where('user_email', $systemChargeEmail)
            ->where('currency', $currency)
            ->where('min_threshold', '<=', $amount)
            ->where('max_threshold', '>=', $amount)
            ->first();

        if (!$systemCharge) {
            throw new RuntimeException('No applicable system charge found for this account and the given amount and currency.');
        }

        $calculatedSystemCharge = $this->calculate($systemCharge->charge_type, (float) $systemCharge->value, $amount);
        $calculatedMerchantCharge = 0.0;

        if ($userType === 'M') {
            $merchantCharge = Charge::active()
                ->where(function ($q) use ($user) {
                    $q->where('merchant_user_id', $user->id)
                      ->orWhere(function ($q2) use ($user) {
                          $q2->whereNull('merchant_user_id')
                             ->where('merchant_user_name', $user->email);
                      });
                })
                ->where('charge_source', 'MERCHANT')
                ->where('currency', $currency)
                ->where('min_threshold', '<=', $amount)
                ->where('max_threshold', '>=', $amount)
                ->first();

            if ($merchantCharge) {
                $calculatedMerchantCharge = $this->calculate($merchantCharge->charge_type, (float) $merchantCharge->value, $amount);
            }
        }

        $totalCharge = $calculatedSystemCharge + $calculatedMerchantCharge;

        return [
            'system_charge' => $calculatedSystemCharge,
            'merchant_charge' => $calculatedMerchantCharge,
            'total_charge' => $totalCharge,
            'total_amount' => $amount + $totalCharge,
            'user_type' => $userType,
        ];
    }

    public function calculate(string $chargeType, float $value, float $amount): float
    {
        if ($chargeType === 'FLAT') {
            return $value;
        }
        if ($chargeType === 'PERCENTAGE') {
            return $amount * ($value / 100);
        }
        throw new RuntimeException('Invalid charge type.');
    }
}
