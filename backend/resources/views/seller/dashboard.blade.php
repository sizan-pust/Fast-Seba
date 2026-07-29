@extends('layouts.seller.app')
@section('title', 'Seller Dashboard')
@section('content')
<div class="page-header d-print-none mb-3"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">Seller Operations</div><h2 class="page-title">Dashboard</h2><div class="text-secondary mt-1">{{ $seller->business_name }} · {{ str($seller->verification_status)->replace('_', ' ')->title() }}</div></div><div class="col-auto ms-auto"><a href="{{ route('seller.resource.products.create') }}" class="btn btn-success">+ Add product</a></div></div></div>
<div class="row row-cards mb-3">
@foreach ([
    'Gross sales' => '৳'.number_format((float)$metrics['gross_sales'],2),
    'Available wallet' => '৳'.number_format((float)($wallet?->availableBalance() ?? 0),2),
    'Orders' => number_format($metrics['orders']),
    'Products' => number_format($metrics['products']),
    'Low stock' => number_format($metrics['low_stock']),
    'Open returns' => number_format($metrics['returns']),
    'Unsettled earnings' => '৳'.number_format((float)$metrics['unsettled'],2),
    'Pending withdrawals' => '৳'.number_format((float)$metrics['withdrawals_pending'],2),
] as $label => $value)
<div class="col-sm-6 col-lg-3"><div class="card card-sm seller-stat-card"><div class="card-body"><div class="subheader">{{ $label }}</div><div class="h2 mb-0">{{ $value }}</div></div></div></div>
@endforeach
</div>
<div class="row row-cards">
<div class="col-lg-8"><div class="card"><div class="card-header"><h3 class="card-title">Sales – last 7 days</h3></div><div class="card-body"><div class="d-flex align-items-end gap-3" style="height:220px">@php($maxSales=max(1,(float)$salesChart->max('value')))@foreach($salesChart as $point)<div class="flex-fill text-center"><div class="text-secondary small mb-1">৳{{ number_format($point['value'],0) }}</div><div class="seller-chart-bar mx-auto" style="height:{{ max(4,($point['value']/$maxSales)*150) }}px;width:65%"></div><div class="small mt-2">{{ $point['label'] }}</div></div>@endforeach</div></div></div></div>
<div class="col-lg-4"><div class="card"><div class="card-header"><h3 class="card-title">Operational snapshot</h3></div><div class="list-group list-group-flush"><a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('seller.resource.stores.index') }}"><span>Stores</span><strong>{{ $metrics['stores'] }}</strong></a><a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('seller.resource.products.index',['verification_status'=>'pending']) }}"><span>Pending products</span><strong>{{ $metrics['pending_products'] }}</strong></a><a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('seller.resource.reviews.index') }}"><span>Reviews</span><strong>{{ $metrics['reviews'] }}</strong></a><a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('seller.resource.advertisements.index') }}"><span>Active ads</span><strong>{{ $metrics['active_ads'] }}</strong></a><a class="list-group-item list-group-item-action d-flex justify-content-between" href="{{ route('seller.resource.pos.index') }}"><span>POS orders</span><strong>{{ $metrics['pos_orders'] }}</strong></a></div></div></div>
<div class="col-12"><div class="card"><div class="card-header"><h3 class="card-title">Recent orders</h3><div class="card-actions"><a href="{{ route('seller.resource.orders.index') }}">View all</a></div></div><div class="table-responsive"><table class="table card-table table-vcenter"><thead><tr><th>Order</th><th>Store</th><th>Customer</th><th>Status</th><th>Subtotal</th><th>Created</th></tr></thead><tbody>@forelse($recentOrders as $item)<tr><td><a href="{{ route('seller.resource.orders.show',$item->id) }}">{{ $item->order?->slug ?? '#'.$item->id }}</a></td><td>{{ $item->store?->name }}</td><td>{{ $item->order?->billing_name ?? $item->order?->user?->name }}</td><td><span class="badge bg-blue-lt">{{ str($item->status)->replace('_',' ')->title() }}</span></td><td>৳{{ number_format((float)$item->subtotal,2) }}</td><td>{{ $item->created_at?->diffForHumans() }}</td></tr>@empty<tr><td colspan="6" class="seller-table-empty">No seller orders yet.</td></tr>@endforelse</tbody></table></div></div></div>
</div>
@endsection
