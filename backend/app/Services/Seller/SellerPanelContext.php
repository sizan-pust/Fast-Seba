<?php

namespace App\Services\Seller;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class SellerPanelContext
{
    public function resolve(User $user, ?int $preferredSellerId = null): Seller
    {
        $owned = Seller::query()
            ->where('user_id', $user->id)
            ->when($preferredSellerId, fn (Builder $query) => $query->whereKey($preferredSellerId))
            ->first();

        if ($owned) {
            return $owned;
        }

        $membership = Seller::query()
            ->whereHas('staff', function (Builder $query) use ($user): void {
                $query->where('users.id', $user->id)
                    ->where('seller_user.status', 'active');
            })
            ->when($preferredSellerId, fn (Builder $query) => $query->whereKey($preferredSellerId))
            ->first();

        if ($membership) {
            return $membership;
        }

        throw (new ModelNotFoundException())->setModel(Seller::class);
    }

    public function isOwner(User $user, Seller $seller): bool
    {
        return (int) $seller->user_id === (int) $user->id;
    }

    public function can(User $user, Seller $seller, ?string $permission): bool
    {
        if ($this->isOwner($user, $seller) || ! $permission) {
            return true;
        }

        return $user->can($permission);
    }
}
