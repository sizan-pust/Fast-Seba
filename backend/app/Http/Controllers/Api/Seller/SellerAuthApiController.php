<?php
namespace App\Http\Controllers\Api\Seller;
use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use App\Types\Api\ApiResponseType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class SellerAuthApiController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data=$request->validate(['email'=>['required','email'],'password'=>['required','string']]);
        $user=User::query()->where('email',$data['email'])->where('access_panel',GuardNameEnum::SELLER->value)->first();
        if(!$user||!Hash::check($data['password'],$user->password)){return ApiResponseType::sendJsonResponse(false,'Invalid seller credentials.',[],401);}
        $seller=Seller::query()->where('user_id',$user->id)->with('stores')->first();
        if(!$seller||$seller->status!=='active'){return ApiResponseType::sendJsonResponse(false,'Seller account is unavailable.',[],403);}
        return response()->json(['success'=>true,'message'=>'Seller login successful.','access_token'=>$user->createToken($user->email)->plainTextToken,'token_type'=>'Bearer','data'=>[
            'id'=>$user->id,'name'=>$user->name,'email'=>$user->email,'mobile'=>$user->mobile,
            'seller_id'=>$seller->id,'business_name'=>$seller->business_name,'verification_status'=>$seller->verification_status,
            'stores'=>$seller->stores->map(fn($store)=>['id'=>$store->id,'name'=>$store->name,'slug'=>$store->slug,'status'=>$store->status])->values(),
        ],'assigned_permissions'=>$user->getAllPermissions()->pluck('name')->values()]);
    }
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return ApiResponseType::sendJsonResponse(true,'Seller logout successful.',[]);
    }
}
