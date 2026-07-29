<?php

namespace App\Providers;

use App\Models\Notification;
use App\Models\Setting;
use App\Services\Seller\SellerPanelContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class SellerPanelServiceProvider extends ServiceProvider
{
    public function boot(SellerPanelContext $context): void
    {
        View::composer(
            ['seller.*', 'layouts.seller.*'],
            function ($view) use ($context): void {
                $settings = [];
                $user = Auth::guard('seller')->user();
                $seller = null;
                $unread = 0;

                try {
                    $settings = Setting::query()->find('system')?->value ?? [];
                } catch (Throwable) {
                    $settings = [];
                }

                if ($user) {
                    try {
                        $seller = $context->resolve(
                            $user,
                            session('seller_id') ? (int) session('seller_id') : null
                        );
                        $unread = Notification::query()
                            ->where('user_id', $user->id)
                            ->where('is_read', false)
                            ->count();
                    } catch (Throwable) {
                        $seller = null;
                        $unread = 0;
                    }
                }

                $view->with([
                    'systemSettings' => $settings,
                    'sellerMenu' => config('seller_menu.sections', []),
                    'sellerUser' => $user,
                    'sellerBusiness' => $seller,
                    'sellerIsOwner' => $user && $seller
                        ? $context->isOwner($user, $seller)
                        : false,
                    'sellerUnreadNotifications' => $unread,
                ]);
            }
        );
    }
}
