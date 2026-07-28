<?php

namespace App\Services;

use App\Models\PaymentGatewayConfig;
use Illuminate\Support\Collection;

class PaymentGatewayManagementService
{
    public function publicGateways(): Collection
    {
        return PaymentGatewayConfig::query()
            ->where('enabled', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PaymentGatewayConfig $gateway) => [
                'code' => $gateway->code,
                'display_name' => $gateway->display_name,
                'enabled' => $gateway->enabled,
                'test_mode' => $gateway->test_mode,
                'public_config' => $gateway->public_config ?? [],
                'supported_currencies' => $gateway->supported_currencies ?? [],
            ]);
    }

    public function adminGateways(): Collection
    {
        return PaymentGatewayConfig::query()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PaymentGatewayConfig $gateway) => [
                'id' => $gateway->id,
                'code' => $gateway->code,
                'display_name' => $gateway->display_name,
                'enabled' => $gateway->enabled,
                'test_mode' => $gateway->test_mode,
                'sort_order' => $gateway->sort_order,
                'public_config' => $gateway->public_config ?? [],
                'supported_currencies' => $gateway->supported_currencies ?? [],
                'has_secret_config' => ! empty($gateway->secret_config),
                'metadata' => $gateway->metadata ?? [],
            ]);
    }

    public function update(string $code, array $data): PaymentGatewayConfig
    {
        $gateway = PaymentGatewayConfig::query()
            ->where('code', $code)
            ->firstOrFail();

        if (! array_key_exists('secret_config', $data)) {
            unset($data['secret_config']);
        }

        $gateway->update($data);

        return $gateway->fresh();
    }
}
