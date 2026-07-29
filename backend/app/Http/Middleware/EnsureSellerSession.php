<?php

namespace App\Http\Middleware;

use App\Enums\GuardNameEnum;
use App\Services\Seller\SellerPanelContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnsureSellerSession
{
    public function __construct(
        private readonly SellerPanelContext $context
    ) {
    }

    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        $guard = Auth::guard('seller');

        if (! $guard->check()) {
            return redirect()->guest(route('seller.login'));
        }

        $user = $guard->user();
        $panel = $user->access_panel instanceof GuardNameEnum
            ? $user->access_panel->value
            : (string) $user->access_panel;

        if ($panel !== GuardNameEnum::SELLER->value || $user->status !== 'active') {
            return $this->reject($request, 'This account cannot access the seller panel.');
        }

        try {
            $seller = $this->context->resolve(
                $user,
                $request->session()->get('seller_id')
            );
        } catch (Throwable) {
            return $this->reject($request, 'No active seller business is linked to this account.');
        }

        if ($seller->status !== 'active') {
            return $this->reject($request, 'This seller business is currently unavailable.');
        }

        $request->session()->put('seller_id', $seller->id);
        $request->attributes->set('seller', $seller);

        return $next($request);
    }

    private function reject(Request $request, string $message): RedirectResponse
    {
        Auth::guard('seller')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('seller.login')
            ->withErrors(['email' => $message]);
    }
}
