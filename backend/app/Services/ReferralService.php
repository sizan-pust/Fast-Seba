<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Referral;
use App\Models\ReferralEarning;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function __construct(
        protected WalletService $wallets
    ) {
    }

    public function ensureCode(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        do {
            $code = 'FS'.Str::upper(Str::random(8));
        } while (User::query()->where('referral_code', $code)->exists());

        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function info(User $user): array
    {
        $code = $this->ensureCode($user);
        $referral = Referral::query()
            ->where('referred_id', $user->id)
            ->with('referrer:id,name')
            ->first();

        return [
            'referral_code' => $code,
            'friends_code' => $user->friends_code,
            'referred_by' => $referral?->referrer?->name,
            'referrals_count' => Referral::query()
                ->where('referrer_id', $user->id)
                ->count(),
            'total_earnings' => ReferralEarning::query()
                ->where('beneficiary_id', $user->id)
                ->where('status', 'success')
                ->sum('earned_amount'),
            'settings' => $this->settings(),
        ];
    }

    public function submit(User $user, string $code): Referral
    {
        return DB::transaction(function () use ($user, $code): Referral {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->friends_code || Referral::query()->where('referred_id', $user->id)->exists()) {
                throw ValidationException::withMessages([
                    'referral_code' => 'A referral code has already been applied.',
                ]);
            }

            $referrer = User::query()
                ->where('referral_code', Str::upper($code))
                ->first();

            if (! $referrer) {
                throw ValidationException::withMessages([
                    'referral_code' => 'Invalid referral code.',
                ]);
            }

            if ($referrer->id === $user->id) {
                throw ValidationException::withMessages([
                    'referral_code' => 'You cannot use your own referral code.',
                ]);
            }

            $settings = $this->settings();

            $referral = Referral::query()->create([
                'referrer_id' => $referrer->id,
                'referred_id' => $user->id,
                'referral_code' => $referrer->referral_code,
                'status' => 'active',
                'settings' => $settings,
            ]);

            $user->forceFill(['friends_code' => $referrer->referral_code])->save();

            return $referral->fresh(['referrer', 'referred']);
        });
    }

    public function earnings(User $user): array
    {
        return ReferralEarning::query()
            ->where('beneficiary_id', $user->id)
            ->with('order:id,slug')
            ->latest()
            ->get()
            ->map(fn (ReferralEarning $earning) => [
                'id' => $earning->id,
                'beneficiary_type' => $earning->beneficiary_type,
                'order_slug' => $earning->order?->slug,
                'earned_amount' => $earning->earned_amount,
                'status' => $earning->status,
                'settled_at' => $earning->settled_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    public function syncDeliveredOrders(): array
    {
        $referrals = Referral::query()
            ->where('status', 'active')
            ->with(['referrer', 'referred'])
            ->get();

        $settled = 0;
        $skipped = 0;

        foreach ($referrals as $referral) {
            $order = Order::query()
                ->where('user_id', $referral->referred_id)
                ->where('status', 'delivered')
                ->oldest('delivered_at')
                ->first();

            if (! $order) {
                $skipped++;
                continue;
            }

            if (ReferralEarning::query()->where('referral_id', $referral->id)->exists()) {
                $skipped++;
                continue;
            }

            DB::transaction(function () use ($referral, $order, &$settled): void {
                $lockedReferral = Referral::query()
                    ->with(['referrer', 'referred'])
                    ->lockForUpdate()
                    ->findOrFail($referral->id);

                if (
                    $lockedReferral->status !== 'active'
                    || ReferralEarning::query()
                        ->where('referral_id', $lockedReferral->id)
                        ->exists()
                ) {
                    return;
                }

                $settings = $lockedReferral->settings ?: $this->settings();

                $rewards = [
                    'referrer' => [
                        'user' => $lockedReferral->referrer,
                        'amount' => (float) ($settings['referrer_bonus'] ?? 50),
                    ],
                    'referee' => [
                        'user' => $lockedReferral->referred,
                        'amount' => (float) ($settings['referee_bonus'] ?? 25),
                    ],
                ];

                foreach ($rewards as $type => $reward) {
                    $user = $reward['user'];
                    $amount = $reward['amount'];

                    if (! $user || $amount <= 0) {
                        continue;
                    }

                    $this->wallets->customerWallet($user);

                    $earning = ReferralEarning::query()->create([
                        'referral_id' => $lockedReferral->id,
                        'beneficiary_id' => $user->id,
                        'beneficiary_type' => $type,
                        'order_id' => $order->id,
                        'order_amount' => $order->final_total,
                        'bonus_method' => 'fixed',
                        'bonus_value' => $amount,
                        'earned_amount' => $amount,
                        'status' => 'pending',
                    ]);

                    $transaction = $this->wallets->credit(
                        $user,
                        $amount,
                        'referral_earning',
                        $earning->id,
                        'FastSheba referral reward'
                    );

                    $earning->update([
                        'wallet_transaction_id' => $transaction->id,
                        'status' => 'success',
                        'settled_at' => now(),
                    ]);

                    $settled++;
                }

                $lockedReferral->update([
                    'status' => 'completed',
                    'rewarded_at' => now(),
                    'completed_at' => now(),
                ]);
            });
        }

        return compact('settled', 'skipped');
    }

    private function settings(): array
    {
        $value = Setting::query()
            ->where('variable', 'referral')
            ->first()?->value;

        return is_array($value)
            ? array_merge([
                'enabled' => true,
                'referrer_bonus' => 50,
                'referee_bonus' => 25,
            ], $value)
            : [
                'enabled' => true,
                'referrer_bonus' => 50,
                'referee_bonus' => 25,
            ];
    }
}
