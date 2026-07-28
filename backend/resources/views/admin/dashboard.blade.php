@extends('layouts.admin.app')

@section('title', 'Dashboard')

@section('content')
    <div class="page-header d-print-none">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">
                    Overview
                </div>
                <h2 class="page-title">
                    FastSheba Dashboard
                </h2>
            </div>

            <div class="col-auto ms-auto d-print-none">
                <form
                    method="GET"
                    action="{{ route('admin.dashboard') }}"
                    class="d-flex align-items-center gap-2"
                >
                    <label
                        for="zone-id"
                        class="text-secondary small"
                    >
                        Delivery zone
                    </label>
                    <select
                        id="zone-id"
                        name="zone_id"
                        class="form-select"
                        onchange="this.form.submit()"
                    >
                        <option value="">
                            All zones
                        </option>
                        @foreach ($zones as $zone)
                            <option
                                value="{{ $zone->id }}"
                                @selected($zoneId === $zone->id)
                            >
                                {{ $zone->name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>

    <div class="row row-deck row-cards">
        @foreach ($metrics as $metric)
            <div class="col-sm-6 col-xl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="subheader">
                                {{ $metric['label'] }}
                            </div>
                            <div class="ms-auto">
                                <span class="avatar avatar-sm bg-{{ $metric['tone'] }}-lt text-{{ $metric['tone'] }}">
                                    <x-admin.icon
                                        :name="$metric['icon']"
                                        size="20"
                                    />
                                </span>
                            </div>
                        </div>
                        <div class="h1 mb-1">
                            {{ $metric['value'] }}
                        </div>
                        <div class="text-secondary">
                            {{ $metric['detail'] }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Revenue vs orders
                        </h3>
                        <p class="card-subtitle">
                            Last 14 days
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    <div
                        id="admin-revenue-chart"
                        class="chart-lg"
                    ></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Order status
                        </h3>
                        <p class="card-subtitle">
                            Current distribution
                        </p>
                    </div>
                </div>
                <div class="card-body">
                    <div
                        id="admin-status-chart"
                        class="chart-lg"
                    ></div>
                </div>
            </div>
        </div>

        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Operational attention
                        </h3>
                        <p class="card-subtitle">
                            Pending work across Phase 1–9 modules
                        </p>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        @foreach ($attention as $attentionItem)
                            <div class="col-sm-6 col-lg-4 col-xl">
                                <a
                                    href="{{ route('admin.module', ['module' => $attentionItem['module']]) }}"
                                    class="card card-sm admin-attention-card"
                                >
                                    <div class="card-body">
                                        <div class="d-flex align-items-center">
                                            <span class="avatar bg-{{ $attentionItem['tone'] }}-lt text-{{ $attentionItem['tone'] }} me-3">
                                                <x-admin.icon
                                                    :name="$attentionItem['icon']"
                                                    size="20"
                                                />
                                            </span>
                                            <div>
                                                <div class="h2 mb-0">
                                                    {{ number_format($attentionItem['value']) }}
                                                </div>
                                                <div class="text-secondary">
                                                    {{ $attentionItem['label'] }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Recent orders
                        </h3>
                        <p class="card-subtitle">
                            Latest marketplace activity
                        </p>
                    </div>
                    <div class="card-actions">
                        <a
                            href="{{ route('admin.module', ['module' => 'orders']) }}"
                            class="btn btn-outline-primary btn-sm"
                        >
                            View orders
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table card-table table-vcenter">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentOrders as $order)
                                <tr>
                                    <td>
                                        <strong>
                                            {{ $order->slug }}
                                        </strong>
                                        <div class="text-secondary small">
                                            {{ $order->created_at?->diffForHumans() }}
                                        </div>
                                    </td>
                                    <td>
                                        {{ $order->user?->name ?? $order->billing_name ?? 'Guest' }}
                                        <div class="text-secondary small">
                                            {{ $order->user?->email ?? $order->email }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge admin-status admin-status-{{ $order->status }}">
                                            {{ str($order->status)
                                                ->replace('_', ' ')
                                                ->title() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-lt">
                                            {{ str($order->payment_status)
                                                ->replace('_', ' ')
                                                ->title() }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        {{ $currencySymbol }}{{ number_format((float) $order->final_total, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td
                                        colspan="5"
                                        class="text-center text-secondary py-5"
                                    >
                                        No orders are available yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <div>
                        <h3 class="card-title">
                            Recent audit activity
                        </h3>
                        <p class="card-subtitle">
                            Administrative and system changes
                        </p>
                    </div>
                </div>

                <div class="list-group list-group-flush">
                    @forelse ($recentActivity as $activity)
                        <div class="list-group-item">
                            <div class="d-flex">
                                <span class="avatar avatar-sm bg-blue-lt text-blue me-3">
                                    <x-admin.icon
                                        name="activity"
                                        size="18"
                                    />
                                </span>
                                <div class="flex-fill">
                                    <div class="fw-medium">
                                        {{ str($activity->action)
                                            ->replace(['.', '_'], ' ')
                                            ->title() }}
                                    </div>
                                    <div class="text-secondary small">
                                        {{ $activity->actor?->name ?? 'System' }}
                                        · {{ $activity->created_at?->diffForHumans() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="list-group-item text-center text-secondary py-5">
                            No audit activity yet.
                        </div>
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
            const revenueChart = document.getElementById(
                'admin-revenue-chart'
            );

            if (revenueChart && window.ApexCharts) {
                new ApexCharts(revenueChart, {
                    chart: {
                        type: 'area',
                        height: 320,
                        toolbar: { show: false },
                        fontFamily: 'inherit'
                    },
                    series: [
                        {
                            name: 'Revenue',
                            type: 'area',
                            data: @json($salesChart['revenue'])
                        },
                        {
                            name: 'Orders',
                            type: 'line',
                            data: @json($salesChart['orders'])
                        }
                    ],
                    xaxis: {
                        categories: @json($salesChart['labels'])
                    },
                    yaxis: [
                        {
                            labels: {
                                formatter: function (value) {
                                    return '{{ $currencySymbol }}'
                                        + Number(value).toLocaleString();
                                }
                            }
                        },
                        {
                            opposite: true,
                            labels: {
                                formatter: function (value) {
                                    return Math.round(value);
                                }
                            }
                        }
                    ],
                    stroke: {
                        curve: 'smooth',
                        width: [2, 3]
                    },
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.30,
                            opacityTo: 0.02,
                            stops: [0, 95, 100]
                        }
                    },
                    dataLabels: { enabled: false },
                    legend: {
                        position: 'top',
                        horizontalAlign: 'right'
                    },
                    grid: {
                        strokeDashArray: 4
                    }
                }).render();
            }

            const statusChart = document.getElementById(
                'admin-status-chart'
            );

            if (statusChart && window.ApexCharts) {
                new ApexCharts(statusChart, {
                    chart: {
                        type: 'donut',
                        height: 320,
                        fontFamily: 'inherit'
                    },
                    series: @json($statusChart['values']),
                    labels: @json($statusChart['labels']),
                    legend: {
                        position: 'bottom'
                    },
                    dataLabels: {
                        enabled: false
                    },
                    plotOptions: {
                        pie: {
                            donut: {
                                size: '70%'
                            }
                        }
                    }
                }).render();
            }
        });
    </script>
@endpush
