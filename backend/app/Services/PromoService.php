<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Promo;
use App\Models\PromoUserUsage;
use App\Models\User;

class PromoService
{
    public function validate(
        ?string $code,
        User $user,
        float $cartTotal,
        iterable $cartItems
    ): array {
        if (! $code) {
            return [
                'success' => true,
                'discount' => 0.0,
                'promo' => null,
                'message' => '',
            ];
        }

        $promo = Promo::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper($code)])
            ->first();

        if (! $promo || ! $promo->isCurrentlyActive()) {
            return $this->failure('Invalid or expired promo code.');
        }

        if ($cartTotal < (float) $promo->min_order_total) {
            return $this->failure(
                'Minimum order total is not reached.',
                $promo
            );
        }

        if ($promo->max_usage_per_user !== null) {
            $usage = PromoUserUsage::query()
                ->where('promo_id', $promo->id)
                ->where('user_id', $user->id)
                ->value('usage_count') ?? 0;

            if ($usage >= $promo->max_usage_per_user) {
                return $this->failure(
                    'Promo usage limit reached.',
                    $promo
                );
            }
        }

        if (! $this->scopeMatches($promo, $cartItems)) {
            return $this->failure(
                'Promo is not applicable to this cart.',
                $promo
            );
        }

        $discount = $promo->discount_type === 'percentage'
            ? $cartTotal * ((float) $promo->discount_amount / 100)
            : (float) $promo->discount_amount;

        if ($promo->max_discount_value !== null) {
            $discount = min(
                $discount,
                (float) $promo->max_discount_value
            );
        }

        $discount = round(min($discount, $cartTotal), 2);

        return [
            'success' => true,
            'discount' => $discount,
            'promo' => $this->promoArray($promo),
            'message' => 'Promo applied successfully.',
        ];
    }

    public function available(User $user): array
    {
        return Promo::query()
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (Promo $promo) use ($user): bool {
                if (! $promo->isCurrentlyActive()) {
                    return false;
                }

                if ($promo->max_usage_per_user === null) {
                    return true;
                }

                $usage = PromoUserUsage::query()
                    ->where('promo_id', $promo->id)
                    ->where('user_id', $user->id)
                    ->value('usage_count') ?? 0;

                return $usage < $promo->max_usage_per_user;
            })
            ->map(fn (Promo $promo) => $this->promoArray($promo))
            ->values()
            ->all();
    }

    private function scopeMatches(
        Promo $promo,
        iterable $cartItems
    ): bool {
        if ($promo->promo_mode === 'global') {
            return true;
        }

        foreach ($cartItems as $item) {
            if (! $item instanceof CartItem) {
                continue;
            }

            if (
                $promo->promo_mode === 'store'
                && $item->store_id === (int) $promo->scope_id
            ) {
                return true;
            }

            if (
                $promo->promo_mode === 'product'
                && $item->product_id === (int) $promo->scope_id
            ) {
                return true;
            }

            if (
                $promo->promo_mode === 'category'
                && $item->product?->category_id
                    === (int) $promo->scope_id
            ) {
                return true;
            }
        }

        return false;
    }

    private function promoArray(Promo $promo): array
    {
        return [
            'id' => $promo->id,
            'code' => $promo->code,
            'description' => $promo->description,
            'start_date' => $promo->start_date,
            'end_date' => $promo->end_date,
            'discount_type' => $promo->discount_type,
            'discount_amount' => $promo->discount_amount,
            'promo_mode' => $promo->promo_mode,
            'min_order_total' => $promo->min_order_total,
            'max_discount_value' => $promo->max_discount_value,
            'status' => $promo->isCurrentlyActive()
                ? 'active'
                : 'expired',
        ];
    }

    private function failure(
        string $message,
        ?Promo $promo = null
    ): array {
        return [
            'success' => false,
            'discount' => 0.0,
            'promo' => $promo ? $this->promoArray($promo) : null,
            'message' => $message,
        ];
    }
}