<?php

namespace App\Http\Middleware;

use App\Enums\GuardNameEnum;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSession
{
    public function handle(
        Request $request,
        Closure $next
    ): Response|RedirectResponse {
        $guard = Auth::guard('admin');

        if (! $guard->check()) {
            return redirect()
                ->guest(route('admin.login'));
        }

        $user = $guard->user();

        $panel = $user->access_panel
            instanceof GuardNameEnum
            ? $user->access_panel->value
            : (string) $user->access_panel;

        if (
            $panel !== GuardNameEnum::ADMIN->value
            || $user->status !== 'active'
        ) {
            $guard->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors([
                    'email' => 'This account cannot access the admin panel.',
                ]);
        }

        return $next($request);
    }
}
