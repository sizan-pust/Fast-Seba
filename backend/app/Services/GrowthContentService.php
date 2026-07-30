<?php

namespace App\Services;

use App\Http\Resources\ProductListResource;
use App\Models\Banner;
use App\Models\FeaturedSection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class GrowthContentService
{
    public function zoneId(?float $latitude, ?float $longitude): ?int
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        return DeliveryZoneService::getZonesAtPoint(
            $latitude,
            $longitude
        )['zone_id'];
    }

    public function banners(
        ?int $zoneId = null,
        ?string $position = null,
        ?int $categoryId = null
    ): Collection {
        return Banner::query()
            ->where('visibility_status', 'published')
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->when($position, function (Builder $query, string $position): void {
                $aliases = match ($position) {
                    'top' => ['top', 'home_top'],
                    'carousel' => ['carousel', 'home_carousel'],
                    'sidebar' => ['sidebar', 'home_sidebar'],
                    default => [$position],
                };

                $query->whereIn('position', $aliases);
            })
            ->when($categoryId, function (Builder $query, int $categoryId): void {
                $query->where(function (Builder $scope) use ($categoryId): void {
                    $scope->where('scope_type', 'global')
                        ->orWhere(function (Builder $categoryScope) use ($categoryId): void {
                            $categoryScope->where('scope_type', 'category')
                                ->where('scope_id', $categoryId);
                        });
                });
            })
            ->when($zoneId, function (Builder $query, int $zoneId): void {
                $query->where(function (Builder $zoneQuery) use ($zoneId): void {
                    $zoneQuery->whereDoesntHave('zones')
                        ->orWhereHas(
                            'zones',
                            fn (Builder $relation) => $relation->where(
                                'delivery_zones.id',
                                $zoneId
                            )
                        );
                });
            })
            ->with(['product', 'category', 'brand', 'zones'])
            ->orderBy('display_order')
            ->get()
            ->map(function (Banner $banner): array {
                $image = $banner->imageUrl();

                return [
                    'id' => $banner->id,
                    'type' => $banner->type,
                    'type_id' => $banner->product_id
                        ?? $banner->category_id
                        ?? $banner->brand_id
                        ?? 0,
                    'title' => $banner->title,
                    'slug' => $banner->slug,
                    'position' => $banner->position,
                    'custom_url' => $banner->custom_url,
                    'image' => $image,
                    'banner_image' => $image,
                    'product_id' => $banner->product_id,
                    'product_slug' => $banner->product?->slug,
                    'category_id' => $banner->category_id,
                    'category_slug' => $banner->category?->slug,
                    'brand_id' => $banner->brand_id,
                    'brand_slug' => $banner->brand?->slug,
                    'metadata' => $banner->metadata ?? [],
                ];
            });
    }

    public function sections(?int $zoneId = null): Collection
    {
        return FeaturedSection::query()
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->when($zoneId, function (Builder $query, int $zoneId): void {
                $query->where(function (Builder $zoneQuery) use ($zoneId): void {
                    $zoneQuery->whereDoesntHave('zones')
                        ->orWhereHas(
                            'zones',
                            fn (Builder $relation) => $relation->where(
                                'delivery_zones.id',
                                $zoneId
                            )
                        );
                });
            })
            ->with(['zones'])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (FeaturedSection $section) => $this->sectionPayload($section, $zoneId));
    }

    public function section(
        string $slug,
        ?int $zoneId = null
    ): array {
        $section = FeaturedSection::query()
            ->where('slug', $slug)
            ->where('status', 'active')
            ->with('zones')
            ->firstOrFail();

        if (
            $zoneId
            && $section->zones->isNotEmpty()
            && ! $section->zones->contains('id', $zoneId)
        ) {
            abort(404);
        }

        return $this->sectionPayload($section, $zoneId);
    }

    private function sectionPayload(
        FeaturedSection $section,
        ?int $zoneId
    ): array {
        $products = $this->sectionProducts($section, $zoneId);
        $style = in_array(
            $section->style,
            ['with_background', 'without_background'],
            true
        )
            ? $section->style
            : (
                $section->background_type === 'image'
                || filled($section->background_color)
                    ? 'with_background'
                    : 'without_background'
            );

        return [
            'id' => $section->id,
            'title' => $section->title,
            'slug' => $section->slug,
            'short_description' => $section->short_description,
            'style' => $style,
            'section_type' => $section->section_type,
            'status' => $section->status,
            'scope_type' => $section->scope_type,
            'scope_id' => $section->scope_id,
            'scope_category_slug' => null,
            'scope_category_title' => null,
            'background_type' => $section->background_type,
            'background_color' => $section->background_color,
            'background_image' => $section->backgroundImageUrl(),
            'desktop_4k_background_image' => $section->backgroundImageUrl(),
            'desktop_fdh_background_image' => $section->backgroundImageUrl(),
            'tablet_background_image' => $section->backgroundImageUrl(),
            'mobile_background_image' => $section->backgroundImageUrl(),
            'text_color' => $section->text_color,
            'sort_order' => $section->sort_order,
            'products' => $products,
            'products_count' => $products->count(),
            'categories' => [],
            'created_at' => $section->created_at?->toIso8601String(),
            'updated_at' => $section->updated_at?->toIso8601String(),
        ];
    }

    private function sectionProducts(
        FeaturedSection $section,
        ?int $zoneId
    ): Collection {
        $limit = max(1, min(50, (int) $section->product_limit));

        if ($section->section_type === 'manual') {
            $ids = $section->products()
                ->limit($limit)
                ->pluck('products.id');

            return $this->productQuery($zoneId)
                ->whereIn('products.id', $ids)
                ->get()
                ->sortBy(fn (Product $product) => $ids->search($product->id))
                ->values()
                ->map(fn (Product $product) => $this->productPayload($product));
        }

        $query = $this->productQuery($zoneId);

        if ($section->section_type === 'top_rated') {
            $query->addSelect([
                'public_rating' => \App\Models\Review::query()
                    ->selectRaw('AVG(rating)')
                    ->whereColumn('reviews.product_id', 'products.id')
                    ->where('status', 'published'),
            ])->orderByDesc('public_rating');
        } elseif ($section->section_type === 'featured') {
            $query->where('featured', true)->latest();
        } else {
            $query->latest();
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (Product $product) => $this->productPayload($product));
    }

    private function productQuery(?int $zoneId): Builder
    {
        return Product::query()
            ->where('status', 'active')
            ->where('verification_status', 'approved')
            ->when($zoneId, function (Builder $query, int $zoneId): void {
                $query->whereHas(
                    'variants.storeProductVariants.store.zones',
                    fn (Builder $zoneQuery) => $zoneQuery->where(
                        'delivery_zones.id',
                        $zoneId
                    )
                );
            })
            ->with([
                'category',
                'brand',
                'seller.owner',
                'badge',
                'variants.attributes.attribute',
                'variants.attributes.attributeValue',
                'variants.storeProductVariants.store',
                'variantAttributes.attribute',
                'variantAttributes.attributeValue',
            ]);
    }

    private function productPayload(Product $product): array
    {
        return (new ProductListResource($product))->resolve(request());
    }
}
