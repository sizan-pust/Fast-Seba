<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\Order;
use App\Models\OrderItemReturn;
use App\Models\Product;
use App\Models\Review;
use App\Models\SellerOrder;
use App\Models\SellerStatement;
use App\Models\SellerWithdrawalRequest;
use App\Models\Store;
use App\Models\StoreProductVariant;
use App\Models\Wallet;
use App\Services\Seller\SellerPanelContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SellerDashboardController extends Controller
{
    public function __construct(
        private readonly SellerPanelContext $context
    ) {
    }

    public function index(Request $request): View
    {
        $user = Auth::guard('seller')->user();
        $seller = $request->attributes->get('seller')
            ?? $this->context->resolve($user);

        $wallet = Wallet::query()
            ->where('user_id', $seller->user_id)
            ->where('type', 'seller')
            ->first();

        $recentOrders = SellerOrder::query()
            ->where('seller_id', $seller->id)
            ->with(['order.user', 'store'])
            ->latest()
            ->limit(8)
            ->get();

        $statusSummary = SellerOrder::query()
            ->where('seller_id', $seller->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $salesByDay = SellerOrder::query()
            ->where('seller_id', $seller->id)
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(created_at) as day, SUM(subtotal) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $chart = collect(range(6, 0))->map(function (int $offset) use ($salesByDay): array {
            $date = now()->subDays($offset);

            return [
                'label' => $date->format('D'),
                'date' => $date->toDateString(),
                'value' => (float) ($salesByDay[$date->toDateString()] ?? 0),
            ];
        });

        return view('seller.dashboard', [
            'seller' => $seller,
            'wallet' => $wallet,
            'recentOrders' => $recentOrders,
            'statusSummary' => $statusSummary,
            'salesChart' => $chart,
            'metrics' => [
                'stores' => Store::query()->where('seller_id', $seller->id)->count(),
                'products' => Product::query()->where('seller_id', $seller->id)->count(),
                'pending_products' => Product::query()->where('seller_id', $seller->id)->where('verification_status', 'pending')->count(),
                'low_stock' => StoreProductVariant::query()
                    ->whereHas('store', fn ($query) => $query->where('seller_id', $seller->id))
                    ->whereColumn('stock', '<=', 'low_stock_threshold')
                    ->count(),
                'orders' => SellerOrder::query()->where('seller_id', $seller->id)->count(),
                'returns' => OrderItemReturn::query()->where('seller_id', $seller->id)->whereNotIn('return_status', ['rejected', 'completed', 'cancelled'])->count(),
                'gross_sales' => SellerOrder::query()->where('seller_id', $seller->id)->sum('subtotal'),
                'unsettled' => SellerStatement::query()->where('seller_id', $seller->id)->where('settlement_status', 'unsettled')->where('direction', 'credit')->sum('amount'),
                'withdrawals_pending' => SellerWithdrawalRequest::query()->where('seller_id', $seller->id)->whereIn('status', ['requested', 'pending', 'processing'])->sum('amount'),
                'reviews' => Review::query()->whereHas('product', fn ($query) => $query->where('seller_id', $seller->id))->count(),
                'active_ads' => AdCampaign::query()->where('seller_id', $seller->id)->whereIn('status', ['active', 'approved'])->count(),
                'pos_orders' => Order::query()->where('source', 'pos')->whereHas('sellerOrders', fn ($query) => $query->where('seller_id', $seller->id))->count(),
            ],
        ]);
    }
}
