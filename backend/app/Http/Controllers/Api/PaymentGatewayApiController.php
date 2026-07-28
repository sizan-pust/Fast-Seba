<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentGatewayManagementService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;

class PaymentGatewayApiController extends Controller
{
    public function __construct(
        protected PaymentGatewayManagementService $gateways
    ) {
    }

    public function index(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(
            true,
            'Payment gateways fetched.',
            $this->gateways->publicGateways()
        );
    }
}
