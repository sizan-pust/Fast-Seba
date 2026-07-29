<!doctype html>
<html lang="en">
<head>@include('layouts.seller.partials.head')</head>
<body>
<div class="page">
    @include('layouts.seller.partials.sidebar')
    <div class="page-wrapper">
        @include('layouts.seller.partials.header')
        <div class="page-body">
            <div class="container-xl">
                @include('layouts.seller.partials.alerts')
                @yield('content')
            </div>
        </div>
        @include('layouts.seller.partials.footer')
    </div>
</div>
@include('layouts.seller.partials.scripts')
</body>
</html>
