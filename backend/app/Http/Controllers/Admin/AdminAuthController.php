<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()
                ->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(
        Request $request
    ): RedirectResponse {
        $credentials = $request->validate([
            'email' => [
                'required',
                'email',
            ],
            'password' => [
                'required',
                'string',
            ],
        ]);

        $remember = $request->boolean('remember');

        $authenticated = Auth::guard('admin')
            ->attempt([
                'email' => $credentials['email'],
                'password' => $credentials['password'],
                'access_panel' =>
                    GuardNameEnum::ADMIN->value,
                'status' => 'active',
            ], $remember);

        if (! $authenticated) {
            return back()
                ->withInput(
                    $request->only('email')
                )
                ->withErrors([
                    'email' => 'Invalid admin credentials.',
                ]);
        }

        $request->session()->regenerate();

        return redirect()
            ->intended(route('admin.dashboard'))
            ->with(
                'success',
                'Welcome back to FastSheba.'
            );
    }

    public function logout(
        Request $request
    ): RedirectResponse {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with(
                'success',
                'You have been signed out.'
            );
    }

    public function showForgotPassword(): View
    {
        return view('admin.auth.forgot-password');
    }

    public function sendResetLink(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()
            ->where(
                'access_panel',
                GuardNameEnum::ADMIN->value
            )
            ->where('status', 'active')
            ->where('email', $data['email'])
            ->first();

        if ($user) {
            $token = Password::broker('users')
                ->createToken($user);

            $user->notify(
                new AdminResetPasswordNotification(
                    $token
                )
            );
        }

        return back()->with(
            'status',
            'When an active admin account matches that email, '
            .'a reset link will be sent.'
        );
    }

    public function showResetPassword(
        Request $request,
        string $token
    ): View {
        return view(
            'admin.auth.reset-password',
            [
                'token' => $token,
                'email' => (string) $request
                    ->query('email', ''),
            ]
        );
    }

    public function resetPassword(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ]);

        $admin = User::query()
            ->where(
                'access_panel',
                GuardNameEnum::ADMIN->value
            )
            ->where('status', 'active')
            ->where('email', $data['email'])
            ->first();

        if (! $admin) {
            return back()
                ->withInput(
                    $request->only('email')
                )
                ->withErrors([
                    'email' =>
                        'The reset link is invalid or expired.',
                ]);
        }

        $status = Password::broker('users')->reset(
            $data,
            function (
                User $user,
                string $password
            ): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' =>
                        Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput(
                    $request->only('email')
                )
                ->withErrors([
                    'email' =>
                        'The reset link is invalid or expired.',
                ]);
        }

        return redirect()
            ->route('admin.login')
            ->with(
                'success',
                'Your admin password has been reset.'
            );
    }
}
