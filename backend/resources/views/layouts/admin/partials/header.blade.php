<header class="navbar navbar-expand-md d-print-none fastsheba-topbar">
    <div class="container-xl">
        <button class="navbar-toggler d-lg-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#admin-sidebar-menu" aria-label="Toggle navigation">
            <x-admin.icon name="menu" />
        </button>

        <div class="navbar-nav flex-row order-md-last ms-auto">
            <div class="nav-item d-none d-md-flex me-2">
                <button type="button" class="nav-link px-0 border-0 bg-transparent" id="admin-theme-toggle" title="Toggle theme">
                    <span data-theme-icon="moon"><x-admin.icon name="moon" /></span>
                    <span data-theme-icon="sun" class="d-none"><x-admin.icon name="sun" /></span>
                </button>
            </div>

            <div class="nav-item d-none d-md-flex me-3">
                <a href="{{ route('admin.manage.notifications.index') }}" class="nav-link px-0 position-relative" title="Notifications">
                    <x-admin.icon name="bell" />
                    @if ($adminUnreadNotifications > 0)
                        <span class="badge bg-red text-red-fg badge-notification badge-pill">{{ min($adminUnreadNotifications, 99) }}</span>
                    @endif
                </a>
            </div>

            <div class="nav-item dropdown">
                <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open admin menu">
                    <span class="avatar avatar-sm fastsheba-avatar">{{ str($adminUser?->name ?? 'A')->substr(0, 1)->upper() }}</span>
                    <div class="d-none d-xl-block ps-2">
                        <div>{{ $adminUser?->name ?? 'Administrator' }}</div>
                        <div class="mt-1 small text-secondary">{{ $adminUser?->email }}</div>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <a href="{{ route('admin.profile.edit') }}" class="dropdown-item">
                        <x-admin.icon name="user" size="18" class="me-2" /> Profile
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">
                            <x-admin.icon name="logout" size="18" class="me-2" /> Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="navbar-nav">
            <div class="nav-item"><span class="nav-link px-0 text-secondary">FastSheba operations</span></div>
        </div>
    </div>
</header>
