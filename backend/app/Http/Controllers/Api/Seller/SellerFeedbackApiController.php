<?php

namespace App\Http\Controllers\Api\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\SellerFeedback;
use App\Services\DeliveryCashFeedbackService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SellerFeedbackApiController extends Controller
{
    public function __construct(
        protected DeliveryCashFeedbackService $feedback
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
        $seller = $this->seller($request);

        $items = SellerFeedback::query()
            ->where('seller_id', $seller->id)
            ->with(['seller'])
            ->latest()
            ->paginate(
                min(
                    100,
                    max(1, (int) $request->input('per_page', 15))
                )
            );

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller feedback fetched.',
            [
                'rating' => $this->feedback->sellerRating($seller),
                'reviews' => $items,
            ]
        );
    }

    public function reply(
        Request $request,
        int $id
    ): JsonResponse {
        $data = $request->validate([
            'reply' => ['required', 'string', 'max:2000'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller feedback replied.',
            $this->feedback->replySellerFeedback(
                $this->seller($request),
                SellerFeedback::query()->findOrFail($id),
                $data['reply']
            )
        );
    }
}
