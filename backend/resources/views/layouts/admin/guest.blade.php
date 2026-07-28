<!doctype html>
<html lang="en">
<head>
    @include('layouts.admin.partials.head')
</head>
<body class="d-flex flex-column">
    <div class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4">
                <a
                    href="{{ route('admin.login') }}"
                    class="fastsheba-auth-brand"
                >
                    <span class="fastsheba-brand-mark">FS</span>
                    <span>
                        <strong>
                            {{ $systemSettings['appName'] ?? 'FastSheba' }}
                        </strong>
                        <small>Admin Console</small>
                    </span>
                </a>
            </div>

            @include('layouts.admin.partials.alerts')

            @yield('content')
        </div>
    </div>

    @include('layouts.admin.partials.scripts')
</body>
</html>
