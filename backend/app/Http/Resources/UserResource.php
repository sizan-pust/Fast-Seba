<?php

namespace App\Http\Resources;

use App\Enums\WalletTypeEnum;
use App\Models\Wallet;
use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    protected ?WalletTypeEnum $walletType = null;

    public function withWalletType(
        WalletTypeEnum|string $type
    ): self {
        $this->walletType = $type instanceof WalletTypeEnum
            ? $type
            : (
                WalletTypeEnum::tryFrom($type)
                ?? WalletTypeEnum::CUSTOMER
            );

        return $this;
    }

    public function toArray(Request $request): array
    {
        $wallet = $this->resolveWallet($request);

        $balance = (float) ($wallet?->balance ?? 0);
        $blockedBalance = (float) ($wallet?->blocked_balance ?? 0);

        $loggedInType = $this->logged_in_type;
        if ($loggedInType instanceof BackedEnum) {
            $loggedInType = $loggedInType->value;
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'mobile' => $this->mobile,
            'country_code' => $this->country_code,
            'country' => $this->country,
            'iso_2' => $this->iso_2,
            'wallet_type' => $wallet?->type instanceof BackedEnum
                ? $wallet->type->value
                : (
                    $wallet?->type
                    ?? $this->currentWalletType($request)->value
                ),
            'wallet_balance' => number_format(
                $balance,
                2,
                '.',
                ''
            ),
            'blocked_balance' => number_format(
                $blockedBalance,
                2,
                '.',
                ''
            ),
            'available_balance' => number_format(
                $balance - $blockedBalance,
                2,
                '.',
                ''
            ),
            'referral_code' => $this->referral_code,
            'friends_code' => $this->friends_code,
            'reward_points' => $this->reward_points ?? '0.00',
            'profile_image' => $this->profile_image,
            'email_verified_at' => $this->email_verified_at
                ?->format('Y-m-d H:i:s'),
            'mobile_verified_at' => $this->mobile_verified_at
                ?->format('Y-m-d H:i:s'),
            'logged_in_type' => $loggedInType,
            'created_at' => $this->created_at
                ?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at
                ?->format('Y-m-d H:i:s'),
        ];
    }

    protected function resolveWallet(Request $request): ?Wallet
    {
        return Wallet::query()
            ->where('user_id', $this->id)
            ->where(
                'type',
                $this->currentWalletType($request)->value
            )
            ->first();
    }

    protected function currentWalletType(
        Request $request
    ): WalletTypeEnum {
        if ($this->walletType instanceof WalletTypeEnum) {
            return $this->walletType;
        }

        $path = ltrim($request->path(), '/');

        return match (true) {
            str_starts_with(
                $path,
                'api/delivery-boy'
            ) => WalletTypeEnum::DELIVERY_BOY,

            str_starts_with(
                $path,
                'api/seller'
            ) => WalletTypeEnum::SELLER,

            default => WalletTypeEnum::CUSTOMER,
        };
    }
}