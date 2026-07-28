<?php

namespace App\Services\Admin;

use App\Enums\GuardNameEnum;
use App\Models\AdCampaign;
use App\Models\DeliveryBoy;
use App\Models\DeliveryBoyWithdrawalRequest;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\OrderItemReturn;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerSubscription;
use App\Models\SellerWithdrawalRequest;
use App\Models\Setting;
use App\Models\Store;
use App\Models\SupportTicket;
use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(?int $zoneId = null): array
    {
        $orderQuery = $this->ordersForZone($zoneId);
        $system = Setting::query()->find('system')?->value ?? [];
        $currencySymbol = $system['currencySymbol'] ?? '৳';

        return [
            'currencySymbol' => $currencySymbol,
            'zoneId' => $zoneId,
            'zones' => DeliveryZone::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name']),
            'metrics' => $this->metrics(
                clone $orderQuery,
                $currencySymbol
            ),
            'salesChart' => $this->salesChart(
                clone $orderQuery,
                14
            ),
            'statusChart' => $this->statusChart(
                clone $orderQuery
            ),
            'attention' => $this->attention(),
            'recentOrders' => (clone $orderQuery)
                ->with(['user:id,name,email'])
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

    private function ordersForZone(
        ?int $zoneId
    ): Builder {
        return Order::query()
            ->when(
                $zoneId,
                fn (Builder $query) => $query->where(
                    'delivery_zone_id',
                    $zoneId
                )
            );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function metrics(
        Builder $orderQuery,
        string $currencySymbol
    ): array {
        $paidRevenue = (float) (clone $orderQuery)
            ->whereIn(
                'payment_status',
                ['paid', 'completed']
            )
            ->sum('final_total');

        $todayOrders = (clone $orderQuery)
            ->whereDate('created_at', today())
            ->count();

        $todayRevenue = (float) (clone $orderQuery)
            ->whereDate('created_at', today())
            ->whereIn(
                'payment_status',
                ['paid', 'completed']
            )
            ->sum('final_total');

        return [
            [
                'label' => 'Total orders',
                'value' => number_format(
                    (clone $orderQuery)->count()
                ),
                'detail' => number_format($todayOrders)
                    .' received today',
                'icon' => 'package',
                'tone' => 'primary',
            ],
            [
                'label' => 'Paid revenue',
                'value' => $currencySymbol
                    .number_format($paidRevenue, 2),
                'detail' => $currencySymbol
                    .number_format($todayRevenue, 2)
                    .' today',
                'icon' => 'cash',
                'tone' => 'green',
            ],
            [
                'label' => 'Customers',
                'value' => number_format(
                    User::query()
                        ->where(
                            'access_panel',
                            GuardNameEnum::WEB->value
                        )
                        ->count()
                ),
                'detail' => number_format(
                    User::query()
                        ->where(
                            'access_panel',
                            GuardNameEnum::WEB->value
                        )
                        ->whereDate(
                            'created_at',
                            today()
                        )
                        ->count()
                ).' joined today',
                'icon' => 'users',
                'tone' => 'azure',
            ],
            [
                'label' => 'Marketplace',
                'value' => number_format(
                    Store::query()->count()
                ).' stores',
                'detail' => number_format(
                    Seller::query()->count()
                ).' sellers · '
                    .number_format(
                        Product::query()->count()
                    ).' products',
                'icon' => 'store',
                'tone' => 'orange',
            ],
        ];
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     orders: array<int, int>,
     *     revenue: array<int, float>
     * }
     */
    private function salesChart(
        Builder $baseQuery,
        int $days
    ): array {
        $labels = [];
        $orders = [];
        $revenue = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = now()
                ->subDays($offset)
                ->startOfDay();

            $labels[] = $date->format('d M');

            $orders[] = (clone $baseQuery)
                ->whereDate(
                    'created_at',
                    $date->toDateString()
                )
                ->count();

            $revenue[] = round(
                (float) (clone $baseQuery)
                    ->whereDate(
                        'created_at',
                        $date->toDateString()
                    )
                    ->whereIn(
                        'payment_status',
                        ['paid', 'completed']
                    )
                    ->sum('final_total'),
                2
            );
        }

        return compact(
            'labels',
            'orders',
            'revenue'
        );
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     values: array<int, int>
     * }
     */
    private function statusChart(
        Builder $orderQuery
    ): array {
        $preferred = [
            'pending',
            'confirmed',
            'preparing',
            'ready',
            'out_for_delivery',
            'delivered',
            'cancelled',
        ];

        $counts = (clone $orderQuery)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $labels = [];
        $values = [];

        foreach ($preferred as $status) {
            $value = (int) ($counts[$status] ?? 0);

            if ($value === 0) {
                continue;
            }

            $labels[] = str($status)
                ->replace('_', ' ')
                ->title()
                ->toString();

            $values[] = $value;
        }

        if ($labels === []) {
            $labels = ['No orders'];
            $values = [1];
        }

        return compact('labels', 'values');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function attention(): array
    {
        return [
            [
                'label' => 'Seller approvals',
                'value' => Seller::query()
                    ->whereIn(
                        'verification_status',
                        ['pending', 'pending_verification']
                    )
                    ->count(),
                'icon' => 'seller',
                'tone' => 'yellow',
                'module' => 'sellers',
            ],
            [
                'label' => 'Store approvals',
                'value' => Store::query()
                    ->whereIn(
                        'verification_status',
                        ['pending', 'pending_verification']
                    )
                    ->count(),
                'icon' => 'store',
                'tone' => 'orange',
                'module' => 'stores',
            ],
            [
                'label' => 'Product approvals',
                'value' => Product::query()
                    ->whereIn(
                        'verification_status',
                        ['pending', 'pending_verification']
                    )
                    ->count(),
                'icon' => 'box',
                'tone' => 'azure',
                'module' => 'products',
            ],
            [
                'label' => 'Return requests',
                'value' => OrderItemReturn::query()
                    ->whereIn(
                        'return_status',
                        [
                            'pending',
                            'requested',
                            'awaiting_seller_response',
                        ]
                    )
                    ->count(),
                'icon' => 'return',
                'tone' => 'purple',
                'module' => 'returns',
            ],
            [
                'label' => 'Prescriptions',
                'value' => Prescription::query()
                    ->whereIn(
                        'status',
                        ['pending', 'under_review']
                    )
                    ->count(),
                'icon' => 'prescription',
                'tone' => 'green',
                'module' => 'prescriptions',
            ],
            [
                'label' => 'Support tickets',
                'value' => SupportTicket::query()
                    ->whereIn(
                        'status',
                        ['open', 'pending']
                    )
                    ->count(),
                'icon' => 'support',
                'tone' => 'red',
                'module' => 'support',
            ],
            [
                'label' => 'Seller withdrawals',
                'value' => SellerWithdrawalRequest::query()
                    ->where('status', 'pending')
                    ->count(),
                'icon' => 'wallet',
                'tone' => 'cyan',
                'module' => 'seller-withdrawals',
            ],
            [
                'label' => 'Rider withdrawals',
                'value' => DeliveryBoyWithdrawalRequest::query()
                    ->where('status', 'pending')
                    ->count(),
                'icon' => 'truck',
                'tone' => 'blue',
                'module' => 'rider-cash',
            ],
            [
                'label' => 'Active subscriptions',
                'value' => SellerSubscription::query()
                    ->whereIn('status', ['trial', 'active'])
                    ->where(function (Builder $query): void {
                        $query->whereNull('ends_at')
                            ->orWhere(
                                'ends_at',
                                '>',
                                now()
                            );
                    })
                    ->count(),
                'icon' => 'credit-card',
                'tone' => 'indigo',
                'module' => 'subscriptions',
            ],
            [
                'label' => 'Active advertisements',
                'value' => AdCampaign::query()
                    ->whereIn(
                        'status',
                        ['approved', 'active']
                    )
                    ->count(),
                'icon' => 'ad',
                'tone' => 'pink',
                'module' => 'advertisements',
            ],
            [
                'label' => 'Delivery partners',
                'value' => DeliveryBoy::query()
                    ->where('status', 'available')
                    ->count(),
                'icon' => 'truck',
                'tone' => 'teal',
                'module' => 'delivery-partners',
            ],
        ];
    }
}
