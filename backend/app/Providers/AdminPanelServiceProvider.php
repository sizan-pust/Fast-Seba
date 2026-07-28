<?php

namespace App\Providers;

use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AdminPanelServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer(
            [
                'admin.*',
                'layouts.admin.*',
                'components.admin.*',
            ],
            function ($view): void {
                $systemSettings = [];

                try {
                    $systemSettings =
                        Setting::query()
                            ->find('system')
                            ?->value
                        ?? [];
                } catch (Throwable) {
                    // Allows maintenance screens before DB readiness.
                }

                $admin = Auth::guard('admin')->user();
                $unreadNotifications = 0;

                if ($admin) {
                    try {
                        $unreadNotifications =
                            Notification::query()
                                ->where(
                                    'user_id',
                                    $admin->id
                                )
                                ->where('is_read', false)
                                ->count();
                    } catch (Throwable) {
                        $unreadNotifications = 0;
                    }
                }

                $view->with([
                    'systemSettings' => $systemSettings,
                    'adminMenu' => config(
                        'admin_menu.sections',
                        []
                    ),
                    'adminUser' => $admin,
                    'adminUnreadNotifications' =>
                        $unreadNotifications,
                ]);
            }
        );
    }
}
