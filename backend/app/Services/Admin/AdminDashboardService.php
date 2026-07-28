<?php

namespace App\Services\Admin;

use App\Enums\GuardNameEnum;
use App\Models\DeliveryBoy;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerStatement;
use App\Models\Store;
use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminDashboardService
{
    public function overview(?int $zoneId = null): array
    {
        $orders = Order::query()->when(
            $zoneId,
            fn (Builder $query) => $query->where('delivery_zone_id', $zoneId)
        );

        $days = 30;
        $from = now()->subDays($days);
        $periodOrders = (clone $orders)->where('created_at', '>=', $from)->count();
        $periodDelivered = (clone $orders)
            ->where('created_at', '>=', $from)
            ->where('status', 'delivered')
            ->count();
        $periodRevenue = (float) (clone $orders)
            ->where('created_at', '>=', $from)
            ->whereIn('payment_status', ['paid', 'completed'])
            ->sum('final_total');
        $newUsers = User::query()
            ->where('access_panel', GuardNameEnum::WEB->value)
            ->where('created_at', '>=', $from)
            ->count();

        return [
            'zoneId' => $zoneId,
            'zones' => DeliveryZone::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'periodDays' => $days,
            'welcome' => [
                'salesRate' => $periodOrders > 0
                    ? round(($periodDelivered / $periodOrders) * 100, 2)
                    : 0,
                'conversionRate' => $periodOrders > 0
                    ? round(($periodDelivered / $periodOrders) * 100, 2)
                    : 0,
                'delivered' => $periodDelivered,
                'orders' => $periodOrders,
            ],
            'revenue' => [
                'total' => $periodRevenue,
                'trend' => $this->trend(
                    $periodRevenue,
                    (float) (clone $orders)
                        ->whereBetween('created_at', [now()->subDays(60), $from])
                        ->whereIn('payment_status', ['paid', 'completed'])
                        ->sum('final_total')
                ),
                'sparkline' => $this->orderSeries(clone $orders, $days, true),
            ],
            'registrations' => [
                'total' => $newUsers,
                'trend' => $this->trend(
                    $newUsers,
                    User::query()
                        ->where('access_panel', GuardNameEnum::WEB->value)
                        ->whereBetween('created_at', [now()->subDays(60), $from])
                        ->count()
                ),
                'sparkline' => $this->userSeries($days),
            ],
            'summaryCards' => [
                [
                    'label' => 'Sellers',
                    'primary' => Seller::query()->count(),
                    'secondary' => Store::query()->where('status', 'online')->count().' active stores',
                    'icon' => 'seller',
                    'tone' => 'primary',
                ],
                [
                    'label' => 'Orders',
                    'primary' => (clone $orders)->count(),
                    'secondary' => (clone $orders)->where('status', 'delivered')->count().' delivered',
                    'icon' => 'package',
                    'tone' => 'green',
                ],
                [
                    'label' => 'Active delivery partners',
                    'primary' => DeliveryBoy::query()->where('status', 'available')->count(),
                    'secondary' => DeliveryBoy::query()->count().' total delivery partners',
                    'icon' => 'truck',
                    'tone' => 'orange',
                ],
                [
                    'label' => 'Products',
                    'primary' => Product::query()->count(),
                    'secondary' => OrderItem::query()->where('status', 'delivered')->sum('quantity').' total sales',
                    'icon' => 'box',
                    'tone' => 'azure',
                ],
            ],
            'salesChart' => $this->combinedSeries(clone $orders, $days),
            'commission' => [
                'total' => (float) SellerStatement::query()
                    ->where('entry_type', 'commission')
                    ->where('posted_at', '>=', $from)
                    ->sum('amount'),
                'orders' => $periodOrders,
                'series' => $this->commissionSeries($days),
            ],
            'recentOrders' => (clone $orders)
                ->with('user:id,name,email')
                ->latest('id')
                ->limit(8)
                ->get(),
            'recentActivity' => SystemAuditLog::query()
                ->with('actor:id,name,email')
                ->latest('created_at')
                ->limit(8)
                ->get(),
        ];
    }

    private function trend(float|int $current, float|int $previous): float
    {
        if ((float) $previous === 0.0) {
            return (float) $current > 0 ? 100.0 : 0.0;
        }

        return round((((float) $current - (float) $previous) / abs((float) $previous)) * 100, 2);
    }

    private function orderSeries(Builder $query, int $days, bool $revenue): array
    {
        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $day = (clone $query)->whereDate('created_at', now()->subDays($offset)->toDateString());
            $series[] = $revenue
                ? round((float) $day->whereIn('payment_status', ['paid', 'completed'])->sum('final_total'), 2)
                : $day->count();
        }

        return $series;
    }

    private function userSeries(int $days): array
    {
        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $series[] = User::query()
                ->where('access_panel', GuardNameEnum::WEB->value)
                ->whereDate('created_at', now()->subDays($offset)->toDateString())
                ->count();
        }

        return $series;
    }

    private function combinedSeries(Builder $query, int $days): array
    {
        $labels = [];
        $orders = [];
        $revenue = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = now()->subDays($offset);
            $labels[] = $date->format('d M');
            $day = (clone $query)->whereDate('created_at', $date->toDateString());
            $orders[] = (clone $day)->count();
            $revenue[] = round(
                (float) (clone $day)
                    ->whereIn('payment_status', ['paid', 'completed'])
                    ->sum('final_total'),
                2
            );
        }

        return compact('labels', 'orders', 'revenue');
    }

    private function commissionSeries(int $days): array
    {
        $series = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $series[] = round(
                (float) SellerStatement::query()
                    ->where('entry_type', 'commission')
                    ->whereDate('posted_at', now()->subDays($offset)->toDateString())
                    ->sum('amount'),
                2
            );
        }

        return $series;
    }
}
