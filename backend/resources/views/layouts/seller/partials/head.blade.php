<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Seller Dashboard') · {{ $systemSettings['appName'] ?? 'FastSheba' }}</title>
<link rel="stylesheet" href="{{ asset('assets/admin-ui/css/tabler.css') }}">
<link rel="stylesheet" href="{{ asset('assets/admin-ui/css/tabler-themes.css') }}">
<link rel="stylesheet" href="{{ asset('assets/admin-ui/css/fastsheba-admin.css') }}">
<link rel="stylesheet" href="{{ asset('assets/admin-ui/css/fastsheba-seller.css') }}">
<script src="{{ asset('assets/admin-ui/js/tabler-theme.min.js') }}"></script>
@stack('styles')
