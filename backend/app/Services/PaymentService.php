<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function variables(): array
    {
        $setting = Setting::query()->where('variable', 'payment')->first();
        $value = $setting?->value ?? [];
        return is_array($value) ? $value : [];
    }

    public function validate(array $data, float $payableAfterWallet): array
    {
        $method = (string) $data['payment_type'];
        $settings = $this->variables();

        if (($settings[$method] ?? false) !== true) {
            throw ValidationException::withMessages(['payment_type' => 'The selected payment method is not enabled.']);
        }

        if ($method === 'wallet' && $payableAfterWallet > 0.001) {
            throw ValidationException::withMessages(['payment_type' => 'Wallet balance is not enough to complete this order.']);
        }

        $online = in_array($method, ['razorpayPayment','stripePayment','paystackPayment','flutterwavePayment'], true);
        if ($online && empty($data['transaction_id'])) {
            throw ValidationException::withMessages(['transaction_id' => 'A verified payment transaction reference is required.']);
        }

        return [
            'status' => ($method === 'wallet' || $online) ? 'completed' : 'pending',
            'transaction_id' => $data['transaction_id'] ?? null,
            'details' => $data['payment_details'] ?? [],
        ];
    }
}
