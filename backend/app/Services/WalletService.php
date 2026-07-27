<?php

namespace App\Services;

use App\Enums\WalletTypeEnum;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public function customerWallet(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->id, 'type' => WalletTypeEnum::CUSTOMER->value],
            ['balance' => 0, 'blocked_balance' => 0, 'currency_code' => 'BDT']
        );
    }

    public function debit(User $user, float $amount, string $referenceType, int $referenceId, string $description): WalletTransaction
    {
        if ($amount <= 0) { throw new RuntimeException('Debit amount must be greater than zero.'); }

        return DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $description): WalletTransaction {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', WalletTypeEnum::CUSTOMER->value)
                ->lockForUpdate()
                ->firstOrFail();

            $opening = (float) $wallet->balance;
            $available = $opening - (float) $wallet->blocked_balance;
            if ($available < $amount) { throw new RuntimeException('Insufficient wallet balance.'); }

            $closing = round($opening - $amount, 2);
            $wallet->update(['balance' => $closing]);

            return WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,'user_id' => $user->id,'type' => 'debit',
                'amount' => $amount,'opening_balance' => $opening,'closing_balance' => $closing,
                'reference_type' => $referenceType,'reference_id' => $referenceId,
                'status' => 'completed','description' => $description,
            ]);
        });
    }

    public function credit(User $user, float $amount, string $referenceType, int $referenceId, string $description): WalletTransaction
    {
        if ($amount <= 0) { throw new RuntimeException('Credit amount must be greater than zero.'); }

        return DB::transaction(function () use ($user, $amount, $referenceType, $referenceId, $description): WalletTransaction {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', WalletTypeEnum::CUSTOMER->value)
                ->lockForUpdate()
                ->firstOrFail();
            $opening = (float) $wallet->balance;
            $closing = round($opening + $amount, 2);
            $wallet->update(['balance' => $closing]);

            return WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,'user_id' => $user->id,'type' => 'credit',
                'amount' => $amount,'opening_balance' => $opening,'closing_balance' => $closing,
                'reference_type' => $referenceType,'reference_id' => $referenceId,
                'status' => 'completed','description' => $description,
            ]);
        });
    }
}
