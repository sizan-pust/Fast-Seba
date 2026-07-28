<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\GlobalProductAttribute;
use App\Models\GlobalProductAttributeValue;
use App\Models\Seller;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SellerAttributeManagementApiController extends Controller
{
    private function seller(Request $request): Seller
    {
        return Seller::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    public function index(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $items = GlobalProductAttribute::query()
            ->where(fn ($query) => $query
                ->whereNull('seller_id')
                ->orWhere('seller_id', $seller->id))
            ->with('values')
            ->orderBy('title')
            ->get();

        return ApiResponseType::sendJsonResponse(true, 'Seller attributes fetched.', $items->map(
            fn ($attribute) => [
                'id' => $attribute->id,
                'seller_id' => $attribute->seller_id,
                'title' => $attribute->title,
                'slug' => $attribute->slug,
                'label' => $attribute->label,
                'swatche_type' => $attribute->swatche_type,
                'is_global' => $attribute->seller_id === null,
                'values' => $attribute->values->map(fn ($value) => [
                    'id' => $value->id,
                    'title' => $value->title,
                    'swatche_value' => $value->swatche_value,
                ])->values(),
            ]
        )->values());
    }

    public function store(Request $request): JsonResponse
    {
        $seller = $this->seller($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'label' => ['required', 'string', 'max:255'],
            'swatche_type' => [
                'nullable',
                Rule::in(['text', 'color', 'image']),
            ],
            'values' => ['nullable', 'array'],
            'values.*.title' => ['required', 'string', 'max:255'],
            'values.*.swatche_value' => ['nullable', 'string', 'max:1000'],
        ]);

        $attribute = GlobalProductAttribute::query()->create([
            'seller_id' => $seller->id,
            'title' => $data['title'],
            'label' => $data['label'],
            'swatche_type' => $data['swatche_type'] ?? 'text',
        ]);

        foreach ($data['values'] ?? [] as $value) {
            $attribute->values()->create($value);
        }

        return ApiResponseType::sendJsonResponse(
            true,
            'Attribute created successfully.',
            $attribute->fresh('values'),
            201
        );
    }

    public function update(
        Request $request,
        int $id
    ): JsonResponse {
        $seller = $this->seller($request);

        $attribute = GlobalProductAttribute::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'label' => ['sometimes', 'string', 'max:255'],
            'swatche_type' => [
                'sometimes',
                Rule::in(['text', 'color', 'image']),
            ],
        ]);

        $attribute->update($data);

        return ApiResponseType::sendJsonResponse(
            true,
            'Attribute updated successfully.',
            $attribute->fresh('values')
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $seller = $this->seller($request);

        $attribute = GlobalProductAttribute::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($id);

        if ($attribute->variantAttributes()->exists()) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Attribute used by variants cannot be deleted.',
                [],
                422
            );
        }

        $attribute->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Attribute deleted successfully.',
            []
        );
    }

    public function storeValue(
        Request $request,
        int $attributeId
    ): JsonResponse {
        $seller = $this->seller($request);

        $attribute = GlobalProductAttribute::query()
            ->where('seller_id', $seller->id)
            ->findOrFail($attributeId);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'swatche_value' => ['nullable', 'string', 'max:1000'],
        ]);

        $value = $attribute->values()->create($data);

        return ApiResponseType::sendJsonResponse(
            true,
            'Attribute value created.',
            $value,
            201
        );
    }

    public function updateValue(
        Request $request,
        int $valueId
    ): JsonResponse {
        $seller = $this->seller($request);

        $value = GlobalProductAttributeValue::query()
            ->whereHas('attribute', fn ($query) => $query->where(
                'seller_id',
                $seller->id
            ))
            ->findOrFail($valueId);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'swatche_value' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $value->update($data);

        return ApiResponseType::sendJsonResponse(
            true,
            'Attribute value updated.',
            $value
        );
    }

    public function destroyValue(Request $request, int $valueId): JsonResponse
    {
        $seller = $this->seller($request);

        $value = GlobalProductAttributeValue::query()
            ->whereHas('attribute', fn ($query) => $query->where(
                'seller_id',
                $seller->id
            ))
            ->findOrFail($valueId);

        if ($value->variantAttributes()->exists()) {
            return ApiResponseType::sendJsonResponse(
                false,
                'Attribute value used by variants cannot be deleted.',
                [],
                422
            );
        }

        $value->delete();

        return ApiResponseType::sendJsonResponse(
            true,
            'Attribute value deleted.',
            []
        );
    }
}
