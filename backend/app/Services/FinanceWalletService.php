<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FinanceWalletService
{
    public function wallet(User $user, string $type): Wallet
    {
        return Wallet::query()->firstOrCreate(
            ['user_id' => $user->id, 'type' => $type],
            [
                'balance' => 0,
                'blocked_balance' => 0,
                'currency_code' => 'BDT',
            ]
        );
    }

    public function availableBalance(Wallet $wallet): float
    {
        return round(
            (float) $wallet->balance - (float) $wallet->blocked_balance,
            2
        );
    }

    public function credit(
        User $user,
        string $walletType,
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new RuntimeException('Credit amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $user,
            $walletType,
            $amount,
            $referenceType,
            $referenceId,
            $description
        ): WalletTransaction {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', $walletType)
                ->lockForUpdate()
                ->first();

            $wallet ??= Wallet::query()->create([
                'user_id' => $user->id,
                'type' => $walletType,
                'balance' => 0,
                'blocked_balance' => 0,
                'currency_code' => 'BDT',
            ]);

            $opening = (float) $wallet->balance;
            $closing = round($opening + $amount, 2);

            $wallet->update(['balance' => $closing]);

            return WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'credit',
                'amount' => $amount,
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'completed',
                'description' => $description,
            ]);
        });
    }

    public function block(
        User $user,
        string $walletType,
        float $amount
    ): Wallet {
        if ($amount <= 0) {
            throw new RuntimeException('Blocked amount must be greater than zero.');
        }

        return DB::transaction(function () use ($user, $walletType, $amount): Wallet {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', $walletType)
                ->lockForUpdate()
                ->firstOrFail();

            if ($this->availableBalance($wallet) < $amount) {
                throw new RuntimeException('Insufficient available wallet balance.');
            }

            $wallet->update([
                'blocked_balance' => round(
                    (float) $wallet->blocked_balance + $amount,
                    2
                ),
            ]);

            return $wallet->fresh();
        });
    }

    public function approveBlockedWithdrawal(
        User $user,
        string $walletType,
        float $amount,
        string $referenceType,
        int $referenceId,
        string $description
    ): WalletTransaction {
        return DB::transaction(function () use (
            $user,
            $walletType,
            $amount,
            $referenceType,
            $referenceId,
            $description
        ): WalletTransaction {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', $walletType)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                (float) $wallet->blocked_balance < $amount
                || (float) $wallet->balance < $amount
            ) {
                throw new RuntimeException('Blocked wallet balance is inconsistent.');
            }

            $opening = (float) $wallet->balance;
            $closing = round($opening - $amount, 2);

            $wallet->update([
                'balance' => $closing,
                'blocked_balance' => round(
                    (float) $wallet->blocked_balance - $amount,
                    2
                ),
            ]);

            return WalletTransaction::query()->create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $amount,
                'opening_balance' => $opening,
                'closing_balance' => $closing,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'completed',
                'description' => $description,
            ]);
        });
    }

    public function releaseBlocked(
        User $user,
        string $walletType,
        float $amount
    ): Wallet {
        return DB::transaction(function () use ($user, $walletType, $amount): Wallet {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('type', $walletType)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->update([
                'blocked_balance' => max(
                    0,
                    round((float) $wallet->blocked_balance - $amount, 2)
                ),
            ]);

            return $wallet->fresh();
        });
    }
}
