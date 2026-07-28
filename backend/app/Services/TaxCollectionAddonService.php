<?php

namespace App\Services;

use App\Models\AddonGroup;
use App\Models\AddonItem;
use App\Models\ProductCollection;
use App\Models\Seller;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\TaxClass;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TaxCollectionAddonService
{
    public function taxClasses(): Collection
    {
        return TaxClass::query()
            ->where('status', 'active')
            ->with('rates')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function calculateTax(
        ?TaxClass $taxClass,
        float $taxableAmount
    ): array {
        if (! $taxClass) {
            return [
                'taxable_amount' => round($taxableAmount, 2),
                'tax_amount' => 0.0,
                'rates' => [],
            ];
        }

        $runningBase = $taxableAmount;
        $totalTax = 0.0;
        $lines = [];

        foreach (
            $taxClass->rates
                ->where('status', 'active')
                ->sortBy('priority') as $rate
        ) {
            $amount = round(
                $runningBase * ((float) $rate->rate / 100),
                2
            );

            $totalTax += $amount;

            if ($rate->compound) {
                $runningBase += $amount;
            }

            $lines[] = [
                'id' => $rate->id,
                'name' => $rate->name,
                'rate' => (float) $rate->rate,
                'compound' => (bool) $rate->compound,
                'amount' => $amount,
            ];
        }

        return [
            'taxable_amount' => round($taxableAmount, 2),
            'tax_amount' => round($totalTax, 2),
            'rates' => $lines,
        ];
    }

    public function collections(): Collection
    {
        return ProductCollection::query()
            ->where('status', 'active')
            ->with([
                'products' => fn ($query) => $query
                    ->where('status', 'active')
                    ->where('verification_status', 'approved')
                    ->with([
                        'brand',
                        'category',
                        'variants.storeProductVariants.store',
                    ]),
            ])
            ->orderBy('sort_order')
            ->get();
    }

    public function saveCollection(
        ?ProductCollection $collection,
        array $data
    ): ProductCollection {
        return DB::transaction(function () use (
            $collection,
            $data
        ): ProductCollection {
            $productIds = $data['product_ids'] ?? null;
            unset($data['product_ids']);

            $data['slug'] = $data['slug']
                ?? Str::slug($data['title']);

            if ($collection) {
                $collection->update($data);
            } else {
                $collection = ProductCollection::query()->create($data);
            }

            if ($productIds !== null) {
                $sync = [];

                foreach (array_values($productIds) as $index => $id) {
                    $sync[$id] = ['sort_order' => $index + 1];
                }

                $collection->products()->sync($sync);
            }

            return $collection->fresh('products');
        });
    }

    public function sellerGroups(Seller $seller): Collection
    {
        return AddonGroup::query()
            ->where('seller_id', $seller->id)
            ->with('items')
            ->orderBy('sort_order')
            ->get();
    }

    public function saveGroup(
        Seller $seller,
        ?AddonGroup $group,
        array $data
    ): AddonGroup {
        return DB::transaction(function () use (
            $seller,
            $group,
            $data
        ): AddonGroup {
            $items = $data['items'] ?? null;
            unset($data['items']);

            $data['slug'] = $data['slug']
                ?? Str::slug($data['title']);

            if ($group) {
                abort_unless(
                    $group->seller_id === $seller->id,
                    404
                );

                $group->update($data);
            } else {
                $group = AddonGroup::query()->create(
                    array_merge($data, ['seller_id' => $seller->id])
                );
            }

            if ($items !== null) {
                foreach ($items as $itemData) {
                    $itemData['slug'] = $itemData['slug']
                        ?? Str::slug($itemData['title']);

                    if (! empty($itemData['id'])) {
                        $item = AddonItem::query()
                            ->where('addon_group_id', $group->id)
                            ->findOrFail($itemData['id']);

                        unset($itemData['id']);
                        $item->update($itemData);
                    } else {
                        unset($itemData['id']);
                        $group->items()->create($itemData);
                    }
                }
            }

            return $group->fresh('items');
        });
    }

    public function attachMatrix(
        Seller $seller,
        array $data
    ): array {
        $store = Store::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($data['store_id']);

        $inventory = StoreProductVariant::query()
            ->where('store_id', $store->id)
            ->where('product_variant_id', $data['product_variant_id'])
            ->firstOrFail();

        $group = AddonGroup::query()
            ->where('seller_id', $seller->id)
            ->with('items')
            ->findOrFail($data['addon_group_id']);

        $validItemIds = $group->items->pluck('id')->map(
            fn ($id) => (int) $id
        )->all();

        $requested = collect($data['items'])
            ->keyBy('addon_item_id');

        foreach ($requested as $itemId => $itemData) {
            if (! in_array((int) $itemId, $validItemIds, true)) {
                throw ValidationException::withMessages([
                    'items' => 'An addon item does not belong to the group.',
                ]);
            }
        }

        DB::transaction(function () use (
            $store,
            $inventory,
            $group,
            $requested
        ): void {
            DB::table('store_product_variant_addons')
                ->where('store_id', $store->id)
                ->where('product_variant_id', $inventory->product_variant_id)
                ->where('addon_group_id', $group->id)
                ->delete();

            foreach ($requested->values() as $index => $itemData) {
                DB::table('store_product_variant_addons')->insert([
                    'store_id' => $store->id,
                    'product_variant_id' => $inventory->product_variant_id,
                    'addon_group_id' => $group->id,
                    'addon_item_id' => $itemData['addon_item_id'],
                    'is_default' => $itemData['is_default'] ?? false,
                    'sort_order' => $itemData['sort_order'] ?? ($index + 1),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('store_addon_items')->updateOrInsert(
                    [
                        'store_id' => $store->id,
                        'addon_item_id' => $itemData['addon_item_id'],
                    ],
                    [
                        'price' => $itemData['price'],
                        'cost' => $itemData['cost'] ?? 0,
                        'stock' => $itemData['stock'] ?? 0,
                        'low_stock_threshold' =>
                            $itemData['low_stock_threshold'] ?? 5,
                        'is_available' =>
                            $itemData['is_available'] ?? true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });

        return $this->variantAddonMatrix(
            $store->id,
            $inventory->product_variant_id
        );
    }

    public function variantAddonMatrix(
        int $storeId,
        int $variantId
    ): array {
        $rows = DB::table('store_product_variant_addons as links')
            ->join(
                'addon_groups as groups',
                'groups.id',
                '=',
                'links.addon_group_id'
            )
            ->join(
                'addon_items as items',
                'items.id',
                '=',
                'links.addon_item_id'
            )
            ->leftJoin(
                'store_addon_items as inventory',
                function ($join): void {
                    $join->on(
                        'inventory.store_id',
                        '=',
                        'links.store_id'
                    )->on(
                        'inventory.addon_item_id',
                        '=',
                        'links.addon_item_id'
                    );
                }
            )
            ->where('links.store_id', $storeId)
            ->where('links.product_variant_id', $variantId)
            ->where('groups.status', 'active')
            ->where('items.status', 'active')
            ->orderBy('groups.sort_order')
            ->orderBy('links.sort_order')
            ->select([
                'links.addon_group_id',
                'links.addon_item_id',
                'links.is_default',
                'groups.title as group_title',
                'groups.selection_type',
                'groups.minimum_selection',
                'groups.maximum_selection',
                'groups.is_required',
                'items.title as item_title',
                'inventory.price',
                'inventory.cost',
                'inventory.stock',
                'inventory.is_available',
            ])
            ->get();

        return $rows
            ->groupBy('addon_group_id')
            ->map(function ($items): array {
                $first = $items->first();

                return [
                    'id' => (int) $first->addon_group_id,
                    'title' => $first->group_title,
                    'selection_type' => $first->selection_type,
                    'minimum_selection' =>
                        (int) $first->minimum_selection,
                    'maximum_selection' =>
                        $first->maximum_selection === null
                            ? null
                            : (int) $first->maximum_selection,
                    'is_required' => (bool) $first->is_required,
                    'items' => $items->map(fn ($item) => [
                        'id' => (int) $item->addon_item_id,
                        'title' => $item->item_title,
                        'price' => (float) ($item->price ?? 0),
                        'cost' => (float) ($item->cost ?? 0),
                        'stock' => (int) ($item->stock ?? 0),
                        'is_available' =>
                            (bool) ($item->is_available ?? false),
                        'is_default' => (bool) $item->is_default,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
