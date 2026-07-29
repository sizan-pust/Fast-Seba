<!doctype html>
<html lang="en">
<head>@include('layouts.seller.partials.head')</head>
<body class="d-flex flex-column seller-auth-page">
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center mb-4">
            <a href="{{ route('seller.login') }}" class="fastsheba-auth-logo">
                <img src="{{ asset('assets/admin-ui/images/fastsheba-logo.png') }}" alt="FastSheba">
            </a>
            <div class="text-secondary mt-2">Seller operations portal</div>
        </div>
        @include('layouts.seller.partials.alerts')
        @yield('content')
    </div>
</div>
@include('layouts.seller.partials.scripts')
</body>
</html>
