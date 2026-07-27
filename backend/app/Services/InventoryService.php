<?php

namespace App\Services;

use App\Models\StoreInventoryLog;
use App\Models\StoreProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class InventoryService
{
    public function changeStock(
        StoreProductVariant $inventory,
        string $changeType,
        int $quantity,
        ?string $reason = null,
        ?User $actor = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): StoreProductVariant {
        if (! in_array($changeType, ['add', 'remove', 'adjust'], true)) {
            throw new InvalidArgumentException(
                'Invalid inventory change type.'
            );
        }

        if ($quantity < 0) {
            throw new InvalidArgumentException(
                'Inventory quantity cannot be negative.'
            );
        }

        return DB::transaction(function () use (
            $inventory,
            $changeType,
            $quantity,
            $reason,
            $actor,
            $referenceType,
            $referenceId
        ): StoreProductVariant {
            $locked = StoreProductVariant::query()
                ->lockForUpdate()
                ->findOrFail($inventory->id);

            $previousStock = (int) $locked->stock;

            $newStock = match ($changeType) {
                'add' => $previousStock + $quantity,
                'remove' => $previousStock - $quantity,
                'adjust' => $quantity,
            };

            if ($newStock < 0) {
                throw new RuntimeException(
                    'Insufficient stock for this operation.'
                );
            }

            $locked->update(['stock' => $newStock]);

            $signedQuantity = match ($changeType) {
                'add' => $quantity,
                'remove' => -$quantity,
                'adjust' => $newStock - $previousStock,
            };

            StoreInventoryLog::query()->create([
                'store_id' => $locked->store_id,
                'store_product_variant_id' => $locked->id,
                'product_variant_id' => $locked->product_variant_id,
                'change_type' => $changeType,
                'quantity' => $signedQuantity,
                'previous_stock' => $previousStock,
                'new_stock' => $newStock,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
}