<?php

namespace App\Services;

use App\Models\GiftCard;
use App\Models\GiftCardRedemption;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GiftCardService
{
    public function __construct(
        protected WalletService $wallets
    ) {
    }

    public function validateCode(string $code, ?User $user = null): GiftCard
    {
        $card = GiftCard::query()
            ->where('code', Str::upper(trim($code)))
            ->where('status', 'active')
            ->firstOrFail();

        if ($card->starts_at && $card->starts_at->isFuture()) {
            throw ValidationException::withMessages([
                'code' => 'This gift card is not active yet.',
            ]);
        }

        if ($card->ends_at && $card->ends_at->isPast()) {
            throw ValidationException::withMessages([
                'code' => 'This gift card has expired.',
            ]);
        }

        if (
            $card->max_redemptions !== null
            && $card->redemption_count >= $card->max_redemptions
        ) {
            throw ValidationException::withMessages([
                'code' => 'This gift card has reached its redemption limit.',
            ]);
        }

        if (
            $user
            && GiftCardRedemption::query()
                ->where('gift_card_id', $card->id)
                ->where('user_id', $user->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'code' => 'You have already redeemed this gift card.',
            ]);
        }

        return $card;
    }

    public function redeem(User $user, string $code): GiftCardRedemption
    {
        return DB::transaction(function () use ($user, $code): GiftCardRedemption {
            $card = GiftCard::query()
                ->where('code', Str::upper(trim($code)))
                ->lockForUpdate()
                ->firstOrFail();

            $this->validateCode($card->code, $user);
            $this->wallets->customerWallet($user);

            $redemption = GiftCardRedemption::query()->create([
                'gift_card_id' => $card->id,
                'user_id' => $user->id,
                'amount' => $card->amount,
                'redeemed_at' => now(),
            ]);

            $transaction = $this->wallets->credit(
                $user,
                (float) $card->amount,
                'gift_card_redemption',
                $redemption->id,
                'Gift card '.$card->code.' redeemed'
            );

            $redemption->update([
                'wallet_transaction_id' => $transaction->id,
            ]);

            $card->increment('redemption_count');

            return $redemption->fresh(['giftCard', 'walletTransaction']);
        });
    }
}
