<!doctype html>
<html lang="en">
<head>
    @include('layouts.admin.partials.head')
</head>
<body>
    <div class="page">
        @include('layouts.admin.partials.sidebar')

        <div class="page-wrapper">
            @include('layouts.admin.partials.header')

            <div class="page-body">
                <div class="container-xl">
                    @include('layouts.admin.partials.alerts')

                    @yield('content')
                </div>
            </div>

            @include('layouts.admin.partials.footer')
        </div>
    </div>

    @include('layouts.admin.partials.scripts')
</body>
</html>
