<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\SellerStatementResource;
use App\Http\Resources\WithdrawalRequestResource;
use App\Models\DeliveryBoyWithdrawalRequest;
use App\Models\SellerStatement;
use App\Models\SellerWithdrawalRequest;
use App\Services\PaymentGatewayManagementService;
use App\Services\SellerFinanceService;
use App\Types\Api\ApiResponseType;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminFinanceApiController extends Controller
{
    public function __construct(
        protected SellerFinanceService $finance,
        protected PaymentGatewayManagementService $gateways
    ) {
    }

    public function sync(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Finance records synchronized.',
            $this->finance->sync()
        );
    }

    public function statements(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = SellerStatement::query()
            ->when($request->filled('seller_id'), fn ($query) => $query->where(
                'seller_id',
                $request->integer('seller_id')
            ))
            ->when($request->filled('status'), fn ($query) => $query->where(
                'settlement_status',
                $request->string('status')->toString()
            ))
            ->when($request->filled('direction'), fn ($query) => $query->where(
                'direction',
                $request->string('direction')->toString()
            ))
            ->with(['seller.owner', 'order'])
            ->latest('posted_at')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Admin seller statements fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => SellerStatementResource::collection($items->items())
                ->resolve($request),
        ]);
    }

    public function settleStatement(Request $request, int $id): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller statement settled.',
            new SellerStatementResource(
                $this->finance->settleStatement($request->user(), $id)
            )
        );
    }

    public function sellerWithdrawals(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = SellerWithdrawalRequest::query()
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                $request->string('status')->toString()
            ))
            ->with(['seller.owner', 'processedBy'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Seller withdrawals fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => WithdrawalRequestResource::collection($items->items())
                ->resolve($request),
        ]);
    }

    public function processSellerWithdrawal(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'remark' => ['nullable', 'string', 'max:1000'],
            'external_transaction_id' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Seller withdrawal processed.',
            new WithdrawalRequestResource(
                $this->finance->processSellerWithdrawal(
                    $request->user(),
                    $id,
                    $data['status'],
                    $data['remark'] ?? null,
                    $data['external_transaction_id'] ?? null
                )
            )
        );
    }

    public function riderWithdrawals(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $items = DeliveryBoyWithdrawalRequest::query()
            ->when($request->filled('status'), fn ($query) => $query->where(
                'status',
                $request->string('status')->toString()
            ))
            ->with(['deliveryBoy.user', 'processedBy'])
            ->latest()
            ->paginate(min(100, max(1, (int) $request->input('per_page', 15))));

        return ApiResponseType::sendJsonResponse(true, 'Delivery withdrawals fetched.', [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
            'data' => WithdrawalRequestResource::collection($items->items())
                ->resolve($request),
        ]);
    }

    public function processRiderWithdrawal(
        Request $request,
        int $id
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'remark' => ['nullable', 'string', 'max:1000'],
            'external_transaction_id' => ['nullable', 'string', 'max:255'],
        ]);

        return ApiResponseType::sendJsonResponse(
            true,
            'Delivery withdrawal processed.',
            new WithdrawalRequestResource(
                $this->finance->processRiderWithdrawal(
                    $request->user(),
                    $id,
                    $data['status'],
                    $data['remark'] ?? null,
                    $data['external_transaction_id'] ?? null
                )
            )
        );
    }

    public function gateways(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return ApiResponseType::sendJsonResponse(
            true,
            'Admin payment gateways fetched.',
            $this->gateways->adminGateways()
        );
    }

    public function updateGateway(
        Request $request,
        string $code
    ): JsonResponse {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'display_name' => ['sometimes', 'string', 'max:255'],
            'enabled' => ['sometimes', 'boolean'],
            'test_mode' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'public_config' => ['sometimes', 'array'],
            'secret_config' => ['sometimes', 'array'],
            'supported_currencies' => ['sometimes', 'array'],
            'supported_currencies.*' => ['string', 'max:8'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $gateway = $this->gateways->update($code, $data);

        return ApiResponseType::sendJsonResponse(true, 'Payment gateway updated.', [
            'id' => $gateway->id,
            'code' => $gateway->code,
            'display_name' => $gateway->display_name,
            'enabled' => $gateway->enabled,
            'test_mode' => $gateway->test_mode,
            'has_secret_config' => ! empty($gateway->secret_config),
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        $panel = $request->user()?->access_panel;

        if ($panel instanceof BackedEnum) {
            $panel = $panel->value;
        }

        abort_unless($panel === GuardNameEnum::ADMIN->value, 403);
    }
}
