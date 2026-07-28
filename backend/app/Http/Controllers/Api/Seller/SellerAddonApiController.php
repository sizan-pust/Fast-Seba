<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\AddonGroup;
use App\Models\Seller;
use App\Services\TaxCollectionAddonService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerAddonApiController extends Controller
{
    public function __construct(
        protected TaxCollectionAddonService $addons
    ) {
    }

    private function seller(Request $request): Seller
    {
        return Seller::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function index(Request $request): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Addon groups fetched.',
            $this->addons->sellerGroups(
                $this->seller($request)
            )
        );
    }

    public function show(
        Request $request,
        int $id
    ): JsonResponse {
        $group = AddonGroup::query()
            ->where('seller_id', $this->seller($request)->id)
            ->with('items')
            ->findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Addon group fetched.',
            $group
        );
    }

    public function store(Request $request): JsonResponse
    {
        $group = $this->addons->saveGroup(
            $this->seller($request),
            null,
            $request->validate($this->rules())
        );

        return ApiResponseType::sendJsonResponse(
            true,
            'Addon group created.',
            $group,
            201
        );
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $group = AddonGroup::query()->findOrFail($id);

        return ApiResponseType::sendJsonResponse(
            true,
            'Addon group updated.',
            $this->addons->saveGroup(
                $this->seller($request),
                $group,
                $request->validate($this->rules(true))
            )
        );
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $group = AddonGroup::query()
            ->where('seller_id', $this->seller($request)->id)
            ->findOrFail($id);

        $used = \Illuminate\Support\Facades\DB::table(
            'store_product_variant_addons'
        )
            ->where('addon_group_id', $group->id)
            ->exists();

        if ($used) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Attached addon group cannot be deleted.',
                [],
                422
            );
        }

        $group->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Addon group deleted.',
            []
        );
    }

    public function attachMatrix(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'product_variant_id' => [
                'required',
                'integer',
                'exists:product_variants,id',
            ],
            'addon_group_id' => [
                'required',
                'integer',
                'exists:addon_groups,id',
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.addon_item_id' => [
                'required',
                'integer',
                'exists:addon_items,id',
            ],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.stock' => ['nullable', 'integer', 'min:0'],
            'items.*.low_stock_threshold' => [
                'nullable',
                'integer',
                'min:0',
            ],
            'items.*.is_available' => ['nullable', 'boolean'],
            'items.*.is_default' => ['nullable', 'boolean'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Product addon matrix saved.',
            $this->addons->attachMatrix(
                $this->seller($request),
                $data
            )
        );
    }

    public function matrix(
        Request $request,
        int $storeId,
        int $variantId
    ): JsonResponse {
        $seller = $this->seller($request);

        \App\Models\Store::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($storeId);

        return ApiResponseType::sendJsonResponse(
            true,
            'Product addon matrix fetched.',
            $this->addons->variantAddonMatrix(
                $storeId,
                $variantId
            )
        );
    }

    private function rules(bool $update = false): array
    {
        return [
            'title' => [
                $update ? 'sometimes' : 'required',
                'string',
                'max:255',
            ],
            'slug' => ['nullable', 'string', 'max:255'],
            'selection_type' => [
                'nullable',
                Rule::in(['single', 'multiple']),
            ],
            'minimum_selection' => ['nullable', 'integer', 'min:0'],
            'maximum_selection' => ['nullable', 'integer', 'min:1'],
            'is_required' => ['nullable', 'boolean'],
            'status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
            'items' => ['nullable', 'array'],
            'items.*.id' => ['nullable', 'integer', 'exists:addon_items,id'],
            'items.*.title' => ['required_with:items', 'string', 'max:255'],
            'items.*.slug' => ['nullable', 'string', 'max:255'],
            'items.*.default_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.default_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.status' => [
                'nullable',
                Rule::in(['active', 'inactive']),
            ],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.metadata' => ['nullable', 'array'],
        ];
    }
}
