<?php
namespace App\Http\Controllers\Api\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\WalletTransactionResource;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class WalletApiController extends Controller
{
    public function __construct(protected WalletService $wallets) {}
    public function getWallet(Request $request): JsonResponse
    {
        $wallet=$this->wallets->customerWallet($request->user());
        return ApiResponseType::sendJsonResponse(true,'Wallet fetched successfully.',[
            'id'=>$wallet->id,'type'=>$wallet->type instanceof \BackedEnum?$wallet->type->value:$wallet->type,
            'balance'=>$wallet->balance,'blocked_balance'=>$wallet->blocked_balance,
            'available_balance'=>$wallet->availableBalance(),'currency_code'=>$wallet->currency_code,
        ]);
    }
    public function getTransactions(Request $request): JsonResponse
    {
        $items=WalletTransaction::query()->where('user_id',$request->user()->id)->latest()->paginate(min(100,max(1,(int)$request->input('per_page',15))));
        return ApiResponseType::sendJsonResponse(true,'Wallet transactions fetched successfully.',[
            'current_page'=>$items->currentPage(),'last_page'=>$items->lastPage(),'per_page'=>$items->perPage(),'total'=>$items->total(),
            'data'=>WalletTransactionResource::collection($items->items())->resolve($request),
        ]);
    }
    public function getTransaction(Request $request,int $id): JsonResponse
    {
        $item=WalletTransaction::query()->where('user_id',$request->user()->id)->findOrFail($id);
        return ApiResponseType::sendJsonResponse(true,'Wallet transaction fetched successfully.',new WalletTransactionResource($item));
    }
    public function prepareWalletRecharge(): JsonResponse { return ApiResponseType::sendJsonResponse(false,'Online wallet recharge is not enabled yet.',[],422); }
    public function deductBalance(): JsonResponse { return ApiResponseType::sendJsonResponse(false,'Direct wallet deduction is not allowed.',[],422); }
}
