<?php
namespace App\Http\Controllers\Api\Seller;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemResource;
use App\Http\Resources\OrderResource;
use App\Models\Seller;
use App\Services\OrderService;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class SellerOrderApiController extends Controller
{
    public function __construct(protected OrderService $orders) {}
    private function seller(Request $request): Seller { return Seller::query()->where('user_id',$request->user()->id)->firstOrFail(); }
    public function index(Request $request): JsonResponse
    {
        $items=$this->orders->sellerOrders($this->seller($request),min(100,max(1,(int)$request->input('per_page',15))),$request->input('status'));
        $data=collect($items->items())->map(fn($so)=>[
            'id'=>$so->id,'status'=>$so->status,'delivery_type'=>$so->delivery_type,'subtotal'=>$so->subtotal,
            'commission_amount'=>$so->commission_amount,'seller_earnings'=>$so->seller_earnings,
            'store'=>['id'=>$so->store->id,'name'=>$so->store->name,'slug'=>$so->store->slug],
            'order'=>(new OrderResource($so->order))->resolve($request),
        ])->values();
        return ApiResponseType::sendJsonResponse(true,'Seller orders fetched successfully.',['current_page'=>$items->currentPage(),'last_page'=>$items->lastPage(),'per_page'=>$items->perPage(),'total'=>$items->total(),'data'=>$data]);
    }
    public function show(Request $request,int $id): JsonResponse
    {
        $so=$this->orders->sellerOrder($this->seller($request),$id);
        return ApiResponseType::sendJsonResponse(true,'Seller order fetched successfully.',['id'=>$so->id,'status'=>$so->status,'delivery_type'=>$so->delivery_type,'subtotal'=>$so->subtotal,'order'=>(new OrderResource($so->order))->resolve($request),'items'=>OrderItemResource::collection($so->items)->resolve($request)]);
    }
    public function enums(): JsonResponse
    {
        return ApiResponseType::sendJsonResponse(true,'Order enums fetched successfully.',['statuses'=>['awaiting_store_response','accepted_by_seller','preparing','ready_for_pickup','rejected_by_seller']]);
    }
    public function updateItemStatus(Request $request,int $itemId): JsonResponse
    {
        $data=$request->validate(['status'=>['required',Rule::in(['accepted_by_seller','preparing','ready_for_pickup','rejected_by_seller'])],'reason'=>['nullable','string','max:500']]);
        $item=$this->orders->updateSellerItem($request->user(),$this->seller($request),$itemId,$data['status'],$data['reason']??null);
        return ApiResponseType::sendJsonResponse(true,'Order item status updated successfully.',new OrderItemResource($item));
    }
}
