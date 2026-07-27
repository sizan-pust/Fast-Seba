<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments) {}
    public function paymentVariables(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(true,'Payment variables fetched successfully.',$this->payments->variables());
    }
}
