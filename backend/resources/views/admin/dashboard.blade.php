@extends('layouts.admin.app')

@section('title', 'Dashboard')

@section('content')
    <div class="card mb-3 border-primary border-opacity-25 admin-zone-card">
        <div class="card-body py-2">
            <form method="GET" action="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2 text-primary fw-bold">
                    <x-admin.icon name="map" size="20" /> Zone
                </div>
                <select name="zone_id" class="form-select form-select-sm admin-zone-select" onchange="this.form.submit()">
                    <option value="">All Zones</option>
                    @foreach ($zones as $zone)
                        <option value="{{ $zone->id }}" @selected($zoneId === $zone->id)>{{ $zone->name }}</option>
                    @endforeach
                </select>
                <div class="d-none d-md-flex align-items-center gap-1 ms-2">
                    @foreach ($zones->take(5) as $zone)
                        <a href="{{ route('admin.dashboard', ['zone_id' => $zone->id]) }}" class="btn btn-sm rounded-pill px-3 py-1 {{ $zoneId === $zone->id ? 'btn-primary' : 'btn-outline-primary' }}">
                            {{ $zone->name }}
                        </a>
                    @endforeach
                </div>
                <div class="ms-auto">
                    <span class="badge bg-secondary-lt">{{ $zoneId ? 'Filtered by zone' : 'Showing all zones' }}</span>
                </div>
            </form>
        </div>
    </div>

    <div class="row row-deck row-cards admin-dashboard-grid">
        <div class="col-lg-6">
            <div class="card admin-welcome-card">
                <div class="card-body">
                    <div class="row h-100">
                        <div class="col-sm-7 d-flex flex-column justify-content-between">
                            <div>
                                <h3 class="h2 mb-1">Welcome Back,</h3>
                                <h3 class="h2 text-primary">{{ $adminUser?->name ?? 'Administrator' }}</h3>
                            </div>
                            <div>
                                <div class="d-flex align-items-center mb-1">
                                    <span class="subheader">Sales</span>
                                    <span class="ms-auto text-secondary small">Last {{ $periodDays }} days</span>
                                </div>
                                <div class="h1 mb-3">{{ number_format($welcome['salesRate'], 2) }}%</div>
                                <div class="d-flex align-items-center mb-2">
                                    <span class="text-secondary">Conversion rate</span>
                                    <span class="ms-auto text-green fw-semibold">{{ number_format($welcome['conversionRate'], 2) }}%</span>
                                </div>
                                <div class="text-secondary mb-2">
                                    {{ number_format($welcome['delivered']) }} delivered out of total orders {{ number_format($welcome['orders']) }}
                                </div>
                                <div class="progress progress-sm">
                                    <div class="progress-bar bg-primary" style="width: {{ min(100, $welcome['conversionRate']) }}%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-5 d-none d-sm-flex align-items-center justify-content-center">
                            <div class="admin-welcome-illustration">
                                <svg viewBox="0 0 240 180" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                    <circle cx="150" cy="78" r="52" fill="#e8f2ff"/>
                                    <path d="M137 75l12 12 25-27" fill="none" stroke="#206bc4" stroke-width="12" stroke-linecap="round" stroke-linejoin="round"/>
                                    <circle cx="149" cy="78" r="33" fill="none" stroke="#206bc4" stroke-width="8"/>
                                    <path d="M44 148c12-43 39-66 78-68 0 44-24 68-78 68z" fill="#dcecff"/>
                                    <circle cx="72" cy="91" r="14" fill="#f4b183"/>
                                    <path d="M55 108c18-12 39-8 51 11l-6 31H53z" fill="#206bc4"/>
                                    <path d="M69 114l28-35M80 119l-10 34M101 150h29" stroke="#182433" stroke-width="7" stroke-linecap="round"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card admin-mini-chart-card">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div>
                            <div class="subheader">Revenue</div>
                            <div class="h1 mb-0">৳{{ number_format($revenue['total'], 2) }}</div>
                        </div>
                        <div class="ms-auto text-secondary small">Last {{ $periodDays }} days</div>
                    </div>
                    <div class="mt-2 text-{{ $revenue['trend'] >= 0 ? 'green' : 'red' }}">
                        {{ number_format(abs($revenue['trend']), 1) }}% {{ $revenue['trend'] >= 0 ? '↑' : '↓' }}
                    </div>
                    <div id="admin-revenue-sparkline"></div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card admin-mini-chart-card">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <div>
                            <div class="subheader">New user registrations</div>
                            <div class="h1 mb-0">{{ number_format($registrations['total']) }}</div>
                        </div>
                        <div class="ms-auto text-secondary small">Last {{ $periodDays }} days</div>
                    </div>
                    <div class="mt-2 text-{{ $registrations['trend'] >= 0 ? 'green' : 'red' }}">
                        {{ number_format(abs($registrations['trend']), 1) }}% {{ $registrations['trend'] >= 0 ? '↑' : '↓' }}
                    </div>
                    <div id="admin-user-sparkline"></div>
                </div>
            </div>
        </div>

        @foreach ($summaryCards as $summary)
            <div class="col-sm-6 col-lg-3">
                <div class="card admin-compact-stat">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <span class="avatar bg-{{ $summary['tone'] }}-lt text-{{ $summary['tone'] }} me-3">
                                <x-admin.icon :name="$summary['icon']" size="22" />
                            </span>
                            <div>
                                <div class="h3 mb-0">{{ number_format($summary['primary']) }} {{ $summary['label'] }}</div>
                                <div class="text-secondary">{{ $summary['secondary'] }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header py-3">
                    <h3 class="card-title">Revenue vs Orders</h3>
                    <div class="card-actions text-secondary small">Last {{ $periodDays }} days</div>
                </div>
                <div class="card-body pb-0">
                    <div class="row text-center mb-3">
                        <div class="col">
                            <div class="h2 mb-0">{{ number_format(array_sum($salesChart['orders'])) }}</div>
                            <div class="text-secondary">Orders</div>
                        </div>
                        <div class="col">
                            <div class="h2 mb-0">৳{{ number_format(array_sum($salesChart['revenue']), 2) }}</div>
                            <div class="text-secondary">Revenue</div>
                        </div>
                        <div class="col">
                            <div class="h2 mb-0">৳{{ array_sum($salesChart['orders']) > 0 ? number_format(array_sum($salesChart['revenue']) / array_sum($salesChart['orders']), 2) : '0.00' }}</div>
                            <div class="text-secondary">Avg. Order Value</div>
                        </div>
                    </div>
                    <div id="admin-revenue-orders-chart"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header py-3">
                    <h3 class="card-title">Enhanced Commissions</h3>
                    <div class="card-actions text-secondary small">Last {{ $periodDays }} days</div>
                </div>
                <div class="card-body pb-0">
                    <div class="row text-center mb-3">
                        <div class="col">
                            <div class="h2 mb-0">৳{{ number_format($commission['total'], 2) }}</div>
                            <div class="text-secondary">Total Commission</div>
                        </div>
                        <div class="col">
                            <div class="h2 mb-0">{{ number_format($commission['orders']) }}</div>
                            <div class="text-secondary">Total Orders</div>
                        </div>
                        <div class="col">
                            <div class="h2 mb-0">৳{{ $commission['orders'] > 0 ? number_format($commission['total'] / $commission['orders'], 2) : '0.00' }}</div>
                            <div class="text-secondary">Avg. Commission</div>
                        </div>
                    </div>
                    <div id="admin-commission-chart"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header py-3">
                    <h3 class="card-title">Recent orders</h3>
                    <div class="card-actions">
                        <a href="{{ route('admin.core.orders.index') }}" class="btn btn-outline-primary btn-sm">View orders</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Payment</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td><a href="{{ route('admin.core.orders.show', $order->id) }}" class="fw-semibold">{{ $order->slug }}</a><div class="text-secondary small">{{ $order->created_at?->diffForHumans() }}</div></td>
                                    <td>{{ $order->user?->name ?? $order->billing_name ?? 'Guest' }}</td>
                                    <td><span class="badge admin-status admin-status-{{ $order->status }}">{{ str($order->status)->replace('_', ' ')->title() }}</span></td>
                                    <td><span class="badge admin-status admin-status-{{ $order->payment_status }}">{{ str($order->payment_status)->replace('_', ' ')->title() }}</span></td>
                                    <td class="text-end">৳{{ number_format((float) $order->final_total, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-secondary py-5">No orders are available yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header py-3"><h3 class="card-title">Recent audit activity</h3></div>
                <div class="list-group list-group-flush">
                    @forelse ($recentActivity as $activity)
                        <div class="list-group-item">
                            <div class="d-flex">
                                <span class="avatar avatar-sm bg-blue-lt text-blue me-3"><x-admin.icon name="activity" size="18" /></span>
                                <div class="flex-fill">
                                    <div class="fw-medium">{{ str($activity->action)->replace(['.', '_'], ' ')->title() }}</div>
                                    <div class="text-secondary small">{{ $activity->actor?->name ?? 'System' }} · {{ $activity->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-center text-secondary py-5">No audit activity yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('assets/admin-ui/js/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    function sparkline(selector, data, color) {
        const element = document.querySelector(selector);
        if (! element || ! window.ApexCharts) return;
        new ApexCharts(element, {
            chart: { type: 'line', height: 145, sparkline: { enabled: true }, fontFamily: 'inherit' },
            series: [{ data: data }], colors: [color], stroke: { curve: 'straight', width: 2 },
            fill: { opacity: 0 }, tooltip: { enabled: true }
        }).render();
    }

    sparkline('#admin-revenue-sparkline', @json($revenue['sparkline']), '#206bc4');
    sparkline('#admin-user-sparkline', @json($registrations['sparkline']), '#f76707');

    const revenueOrders = document.querySelector('#admin-revenue-orders-chart');
    if (revenueOrders && window.ApexCharts) {
        new ApexCharts(revenueOrders, {
            chart: { type: 'line', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [
                { name: 'Orders', type: 'column', data: @json($salesChart['orders']) },
                { name: 'Revenue', type: 'line', data: @json($salesChart['revenue']) }
            ],
            xaxis: { categories: @json($salesChart['labels']) },
            stroke: { width: [0, 3], curve: 'smooth' }, colors: ['#4299e1', '#2fb344'],
            dataLabels: { enabled: false }, grid: { strokeDashArray: 4 }, legend: { position: 'top' }
        }).render();
    }

    const commissionChart = document.querySelector('#admin-commission-chart');
    if (commissionChart && window.ApexCharts) {
        new ApexCharts(commissionChart, {
            chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Commission', data: @json($commission['series']) }],
            xaxis: { categories: @json($salesChart['labels']) }, colors: ['#f59f00'],
            stroke: { curve: 'smooth', width: 2 }, dataLabels: { enabled: false },
            fill: { type: 'gradient', gradient: { opacityFrom: .28, opacityTo: .02 } },
            grid: { strokeDashArray: 4 }
        }).render();
    }
});
</script>
@endpush
