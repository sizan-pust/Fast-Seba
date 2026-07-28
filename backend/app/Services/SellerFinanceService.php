<?php

namespace App\Services;

use App\Models\DeliveryBoyAssignment;
use App\Models\DeliveryBoyWithdrawalRequest;
use App\Models\RefundTransaction;
use App\Models\Seller;
use App\Models\SellerOrder;
use App\Models\SellerStatement;
use App\Models\SellerWithdrawalRequest;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SellerFinanceService
{
    public function __construct(
        protected FinanceWalletService $wallets
    ) {
    }

    public function sync(): array
    {
        $sellerCredits = 0;
        $sellerDebits = 0;
        $riderCredits = 0;

        SellerOrder::query()
            ->whereHas('order', fn ($query) => $query->where('status', 'delivered'))
            ->with(['order', 'seller.owner'])
            ->chunkById(100, function ($items) use (&$sellerCredits): void {
                foreach ($items as $sellerOrder) {
                    $statement = SellerStatement::query()->firstOrCreate(
                        [
                            'seller_id' => $sellerOrder->seller_id,
                            'reference_type' => 'seller_order',
                            'reference_id' => $sellerOrder->id,
                            'entry_type' => 'order_earning',
                        ],
                        [
                            'seller_order_id' => $sellerOrder->id,
                            'order_id' => $sellerOrder->order_id,
                            'direction' => 'credit',
                            'amount' => $sellerOrder->seller_earnings,
                            'currency_code' => 'BDT',
                            'description' => 'Seller earning for '.$sellerOrder->order?->slug,
                            'posted_at' => $sellerOrder->order?->delivered_at ?? now(),
                            'settlement_status' => 'unsettled',
                        ]
                    );

                    if ($statement->wasRecentlyCreated) {
                        $sellerCredits++;
                    }
                }
            });

        RefundTransaction::query()
            ->where('status', 'completed')
            ->with('orderReturn')
            ->chunkById(100, function ($items) use (&$sellerDebits): void {
                foreach ($items as $refund) {
                    $return = $refund->orderReturn;

                    if (! $return) {
                        continue;
                    }

                    $statement = SellerStatement::query()->firstOrCreate(
                        [
                            'seller_id' => $return->seller_id,
                            'reference_type' => 'refund_transaction',
                            'reference_id' => $refund->id,
                            'entry_type' => 'return_refund',
                        ],
                        [
                            'order_id' => $refund->order_id,
                            'return_id' => $return->id,
                            'direction' => 'debit',
                            'amount' => $refund->amount,
                            'currency_code' => $refund->currency,
                            'description' => 'Customer refund debit',
                            'posted_at' => $refund->created_at ?? now(),
                            'settlement_status' => 'unsettled',
                        ]
                    );

                    if ($statement->wasRecentlyCreated) {
                        $sellerDebits++;
                    }
                }
            });

        DeliveryBoyAssignment::query()
            ->where('status', 'delivered')
            ->where('payment_status', 'completed')
            ->with('deliveryBoy.user')
            ->chunkById(100, function ($items) use (&$riderCredits): void {
                foreach ($items as $assignment) {
                    $user = $assignment->deliveryBoy?->user;

                    if (! $user || (float) $assignment->total_earnings <= 0) {
                        continue;
                    }

                    $alreadyCredited = \App\Models\WalletTransaction::query()
                        ->where('reference_type', 'delivery_assignment')
                        ->where('reference_id', $assignment->id)
                        ->where('type', 'credit')
                        ->exists();

                    if ($alreadyCredited) {
                        continue;
                    }

                    $this->wallets->credit(
                        $user,
                        'delivery_boy',
                        (float) $assignment->total_earnings,
                        'delivery_assignment',
                        $assignment->id,
                        'Delivery earning for assignment #'.$assignment->id
                    );

                    $riderCredits++;
                }
            });

        return compact('sellerCredits', 'sellerDebits', 'riderCredits');
    }

    public function sellerStatements(
        Seller $seller,
        int $perPage,
        ?string $status = null,
        ?string $direction = null
    ): LengthAwarePaginator {
        return SellerStatement::query()
            ->where('seller_id', $seller->id)
            ->when($status, fn ($query) => $query->where('settlement_status', $status))
            ->when($direction, fn ($query) => $query->where('direction', $direction))
            ->with('order')
            ->latest('posted_at')
            ->paginate($perPage);
    }

    public function settleStatement(
        User $admin,
        int $statementId
    ): SellerStatement {
        return DB::transaction(function () use ($admin, $statementId): SellerStatement {
            $statement = SellerStatement::query()
                ->with('seller.owner')
                ->lockForUpdate()
                ->findOrFail($statementId);

            if ($statement->settlement_status !== 'unsettled') {
                throw ValidationException::withMessages([
                    'statement' => 'Statement has already been settled.',
                ]);
            }

            $sellerUser = $statement->seller?->owner;

            if (! $sellerUser) {
                throw ValidationException::withMessages([
                    'seller' => 'Seller owner is unavailable.',
                ]);
            }

            if ($statement->direction === 'credit') {
                $this->wallets->credit(
                    $sellerUser,
                    'seller',
                    (float) $statement->amount,
                    'seller_statement',
                    $statement->id,
                    $statement->description ?: 'Seller statement settlement'
                );
            } else {
                $wallet = $this->wallets->wallet($sellerUser, 'seller');

                if ($this->wallets->availableBalance($wallet) < (float) $statement->amount) {
                    throw ValidationException::withMessages([
                        'wallet' => 'Seller wallet balance is insufficient for this debit.',
                    ]);
                }

                $this->wallets->block(
                    $sellerUser,
                    'seller',
                    (float) $statement->amount
                );

                $this->wallets->approveBlockedWithdrawal(
                    $sellerUser,
                    'seller',
                    (float) $statement->amount,
                    'seller_statement_debit',
                    $statement->id,
                    $statement->description ?: 'Seller statement debit'
                );
            }

            $statement->update([
                'settlement_status' => 'settled',
                'settled_at' => now(),
                'settlement_reference' => 'SET-'.now()->format('YmdHis').'-'.Str::upper(Str::random(5)),
                'settled_by' => $admin->id,
            ]);

            return $statement->fresh(['order', 'seller.owner']);
        });
    }

    public function requestSellerWithdrawal(
        Seller $seller,
        float $amount,
        ?string $note
    ): SellerWithdrawalRequest {
        $user = $seller->owner()->firstOrFail();

        return DB::transaction(function () use ($seller, $user, $amount, $note): SellerWithdrawalRequest {
            $this->wallets->block($user, 'seller', $amount);

            return SellerWithdrawalRequest::query()->create([
                'user_id' => $user->id,
                'seller_id' => $seller->id,
                'amount' => $amount,
                'status' => 'pending',
                'request_note' => $note,
            ]);
        });
    }

    public function requestRiderWithdrawal(
        \App\Models\DeliveryBoy $rider,
        float $amount,
        ?string $note
    ): DeliveryBoyWithdrawalRequest {
        $user = $rider->user()->firstOrFail();

        return DB::transaction(function () use ($rider, $user, $amount, $note): DeliveryBoyWithdrawalRequest {
            $this->wallets->block($user, 'delivery_boy', $amount);

            return DeliveryBoyWithdrawalRequest::query()->create([
                'user_id' => $user->id,
                'delivery_boy_id' => $rider->id,
                'amount' => $amount,
                'status' => 'pending',
                'request_note' => $note,
            ]);
        });
    }

    public function processSellerWithdrawal(
        User $admin,
        int $id,
        string $status,
        ?string $remark,
        ?string $externalTransactionId
    ): SellerWithdrawalRequest {
        return DB::transaction(function () use (
            $admin,
            $id,
            $status,
            $remark,
            $externalTransactionId
        ): SellerWithdrawalRequest {
            $request = SellerWithdrawalRequest::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Withdrawal request has already been processed.',
                ]);
            }

            $transaction = null;

            if ($status === 'approved') {
                $transaction = $this->wallets->approveBlockedWithdrawal(
                    $request->user,
                    'seller',
                    (float) $request->amount,
                    'seller_withdrawal',
                    $request->id,
                    'Seller withdrawal approved'
                );
            } else {
                $this->wallets->releaseBlocked(
                    $request->user,
                    'seller',
                    (float) $request->amount
                );
            }

            $request->update([
                'status' => $status,
                'admin_remark' => $remark,
                'processed_at' => now(),
                'processed_by' => $admin->id,
                'wallet_transaction_id' => $transaction?->id,
                'external_transaction_id' => $externalTransactionId,
            ]);

            return $request->fresh(['user', 'seller', 'processedBy']);
        });
    }

    public function processRiderWithdrawal(
        User $admin,
        int $id,
        string $status,
        ?string $remark,
        ?string $externalTransactionId
    ): DeliveryBoyWithdrawalRequest {
        return DB::transaction(function () use (
            $admin,
            $id,
            $status,
            $remark,
            $externalTransactionId
        ): DeliveryBoyWithdrawalRequest {
            $request = DeliveryBoyWithdrawalRequest::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($id);

            if ($request->status !== 'pending') {
                throw ValidationException::withMessages([
                    'withdrawal' => 'Withdrawal request has already been processed.',
                ]);
            }

            $transaction = null;

            if ($status === 'approved') {
                $transaction = $this->wallets->approveBlockedWithdrawal(
                    $request->user,
                    'delivery_boy',
                    (float) $request->amount,
                    'delivery_boy_withdrawal',
                    $request->id,
                    'Delivery partner withdrawal approved'
                );
            } else {
                $this->wallets->releaseBlocked(
                    $request->user,
                    'delivery_boy',
                    (float) $request->amount
                );
            }

            $request->update([
                'status' => $status,
                'admin_remark' => $remark,
                'processed_at' => now(),
                'processed_by' => $admin->id,
                'wallet_transaction_id' => $transaction?->id,
                'external_transaction_id' => $externalTransactionId,
            ]);

            return $request->fresh(['user', 'deliveryBoy', 'processedBy']);
        });
    }
}
