@php
    $isSuperAdmin = $adminUser
        ? $adminUser->hasRole('Super Admin', 'admin')
        : false;
@endphp

<aside class="navbar navbar-vertical navbar-expand-lg navbar-dark" id="admin-sidebar">
    <div class="container-fluid">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#admin-sidebar-menu" aria-controls="admin-sidebar-menu" aria-expanded="false" aria-label="Toggle navigation">
            <x-admin.icon name="menu" />
        </button>

        <h1 class="navbar-brand navbar-brand-autodark">
            <a href="{{ route('admin.dashboard') }}" class="fastsheba-brand">
                <span class="fastsheba-sidebar-logo">
                    <img src="{{ asset('assets/admin-ui/images/fastsheba-logo.png') }}" alt="FastSheba">
                </span>
            </a>
        </h1>

        <div class="collapse navbar-collapse" id="admin-sidebar-menu">
            <ul class="navbar-nav pt-lg-2">
                @foreach ($adminMenu as $section)
                    <li class="nav-item mt-3 mb-1">
                        <span class="nav-link disabled admin-menu-section">{{ $section['title'] }}</span>
                    </li>

                    @foreach ($section['items'] ?? [] as $item)
                        @php
                            $permission = $item['permission'] ?? null;
                            $visible = $isSuperAdmin || ! $permission || $adminUser?->can($permission);
                            $isLive = (bool) ($item['live'] ?? false);

                            if (isset($item['route'])) {
                                $url = route($item['route']);
                                $active = request()->routeIs($item['active'] ?? $item['route']);
                            } elseif ($isLive) {
                                $group = $item['group'] ?? 'core';
                                $url = route('admin.'.$group.'.'.$item['module'].'.index');
                                $active = request()->routeIs('admin.'.$group.'.'.$item['module'].'.*');
                            } else {
                                $url = route('admin.module', ['module' => $item['module']]);
                                $active = request()->routeIs('admin.module')
                                    && request()->route('module') === $item['module'];
                            }
                        @endphp

                        @continue(! $visible)

                        <li class="nav-item">
                            <a class="nav-link {{ $active ? 'active' : '' }}" href="{{ $url }}">
                                <span class="nav-link-icon d-md-none d-lg-inline-block">
                                    <x-admin.icon :name="$item['icon'] ?? 'circle'" />
                                </span>
                                <span class="nav-link-title">{{ $item['title'] }}</span>
                                @if (! $isLive && ! isset($item['route']))
                                    <span class="badge bg-white-lt ms-auto">Next</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    </div>
</aside>
