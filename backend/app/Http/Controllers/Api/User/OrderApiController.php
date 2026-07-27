<?php
namespace App\Http\Controllers\Api\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\CreateOrderRequest;
use App\Http\Resources\OrderPaymentResource;
use App\Http\Resources\OrderResource;
use App\Models\OrderPaymentTransaction;
use App\Services\OrderService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class OrderApiController extends Controller
{
    public function __construct(protected OrderService $orders) {}
    public function createOrder(CreateOrderRequest $request): JsonResponse
    {
        $order=$this->orders->create($request->user(),$request->validated());
        return ApiResponseType::sendJsonResponse(true,'Order created successfully.',new OrderResource($order));
    }
    public function getUserOrders(Request $request): JsonResponse
    {
        $orders=$this->orders->userOrders($request->user(),min(100,max(1,(int)$request->input('per_page',15))),$request->only(['status','date_range','order_type']));
        return ApiResponseType::sendJsonResponse(true,$orders->total()?'Orders fetched successfully.':'Orders not found.',[
            'current_page'=>$orders->currentPage(),'last_page'=>$orders->lastPage(),'per_page'=>$orders->perPage(),'total'=>$orders->total(),
            'data'=>OrderResource::collection($orders->items())->resolve($request),
        ]);
    }
    public function getOrder(Request $request,string $orderSlug): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(true,'Order fetched successfully.',new OrderResource($this->orders->userOrder($request->user(),$orderSlug)));
    }
    public function cancelOrderItem(Request $request,int $orderItemId): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(true,'Order item cancelled successfully.',new OrderResource($this->orders->cancelItem($request->user(),$orderItemId)));
    }
    public function reorder(Request $request,int $orderId): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(true,'Reorder processed.',$this->orders->reorder($request->user(),$orderId));
    }
    public function getTransactions(Request $request): JsonResponse
    {
        $query=OrderPaymentTransaction::query()->where('user_id',$request->user()->id)->with('order')->latest();
        if($request->filled('payment_status')){$query->where('payment_status',$request->string('payment_status'));}
        if($request->filled('search')){$search=$request->string('search')->toString();$query->where(fn($q)=>$q->where('transaction_id','like','%'.$search.'%')->orWhereHas('order',fn($o)=>$o->where('slug','like','%'.$search.'%')));}
        $items=$query->paginate(min(100,max(1,(int)$request->input('per_page',15))));
        return ApiResponseType::sendJsonResponse(true,'Transactions fetched successfully.',[
            'current_page'=>$items->currentPage(),'last_page'=>$items->lastPage(),'per_page'=>$items->perPage(),'total'=>$items->total(),
            'data'=>OrderPaymentResource::collection($items->items())->resolve($request),
        ]);
    }
    public function getTransaction(Request $request,int $id): JsonResponse
    {
        $transaction=OrderPaymentTransaction::query()->where('user_id',$request->user()->id)->with('order')->findOrFail($id);
        return ApiResponseType::sendJsonResponse(true,'Transaction fetched successfully.',new OrderPaymentResource($transaction));
    }
    public function getOrderDeliveryBoyLocation(Request $request,string $orderSlug): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(true,'Delivery partner is not assigned yet.',['assigned'=>false,'location'=>null]);
    }
    public function returnOrderItem(Request $request,int $orderItemId): JsonResponse { return ApiResponseType::sendJsonResponse(false,'Returns will be enabled in the delivery and returns phase.',[],422); }
    public function cancelReturnRequest(Request $request,int $orderItemId): JsonResponse { return ApiResponseType::sendJsonResponse(false,'No active return request was found.',[],422); }
}
