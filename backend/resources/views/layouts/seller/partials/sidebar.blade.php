@php
    $ownerAccess = $sellerIsOwner ?? false;
@endphp
<aside class="navbar navbar-vertical navbar-expand-lg navbar-dark seller-sidebar" id="seller-sidebar">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#seller-sidebar-menu" aria-label="Toggle navigation"><x-admin.icon name="menu" /></button>
        <h1 class="navbar-brand navbar-brand-autodark">
            <a href="{{ route('seller.dashboard') }}" class="fastsheba-brand">
                <span class="fastsheba-sidebar-logo"><img src="{{ asset('assets/admin-ui/images/fastsheba-logo.png') }}" alt="FastSheba"></span>
            </a>
        </h1>
        <div class="seller-business-summary px-3 pb-3 text-white-50">
            <div class="fw-semibold text-white text-truncate">{{ $sellerBusiness?->business_name ?? 'Seller business' }}</div>
            <div class="small">{{ str($sellerBusiness?->verification_status ?? 'pending')->replace('_', ' ')->title() }}</div>
        </div>
        <div class="collapse navbar-collapse" id="seller-sidebar-menu">
            <ul class="navbar-nav pt-lg-2">
                @foreach ($sellerMenu as $section)
                    <li class="nav-item mt-3 mb-1"><span class="nav-link disabled admin-menu-section">{{ $section['title'] }}</span></li>
                    @foreach ($section['items'] ?? [] as $item)
                        @php
                            $permission = $item['permission'] ?? null;
                            $visible = $ownerAccess || ! $permission || $sellerUser?->can($permission);
                            if (isset($item['route'])) {
                                $url = route($item['route']);
                                $active = request()->routeIs($item['active'] ?? $item['route']);
                            } else {
                                $url = route('seller.resource.'.$item['module'].'.index');
                                $active = request()->routeIs('seller.resource.'.$item['module'].'.*');
                            }
                        @endphp
                        @continue(! $visible)
                        <li class="nav-item">
                            <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ $url }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block"><x-admin.icon :name="$item['icon'] ?? 'circle'" /></span>
                                <span class="nav-link-title">{{ $item['title'] }}</span>
                                @if (($item['module'] ?? null) === 'notifications' && ($sellerUnreadNotifications ?? 0) > 0)
                                    <span class="badge bg-green-lt ms-auto">{{ min($sellerUnreadNotifications, 99) }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </div>
</aside>
