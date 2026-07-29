<?php

namespace App\Http\Controllers\Seller;

use App\Enums\GuardNameEnum;
use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\Store;
use App\Models\User;
use App\Notifications\SellerResetPasswordNotification;
use App\Services\FinanceWalletService;
use App\Services\Seller\SellerPanelContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Throwable;

class SellerAuthController extends Controller
{
    public function __construct(
        private readonly SellerPanelContext $context,
        private readonly FinanceWalletService $wallets
    ) {
    }

    public function showLogin(): View|RedirectResponse
    {
        if (Auth::guard('seller')->check()) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $authenticated = Auth::guard('seller')->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'access_panel' => GuardNameEnum::SELLER->value,
            'status' => 'active',
        ], $request->boolean('remember'));

        if (! $authenticated) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'Invalid seller credentials.',
            ]);
        }

        try {
            $seller = $this->context->resolve(Auth::guard('seller')->user());

            if ($seller->status !== 'active') {
                throw new \RuntimeException('Seller business is unavailable.');
            }
        } catch (Throwable) {
            Auth::guard('seller')->logout();

            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'No active seller business is linked to this account.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('seller_id', $seller->id);

        return redirect()->intended(route('seller.dashboard'))
            ->with('success', 'Welcome back to the FastSheba seller panel.');
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::guard('seller')->check()) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'mobile' => ['required', 'string', 'max:32', 'unique:users,mobile'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'business_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_license_number' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'store_name' => ['nullable', 'string', 'max:255'],
        ]);

        [$user, $seller] = DB::transaction(function () use ($data): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'mobile' => $data['mobile'],
                'password' => Hash::make($data['password']),
                'status' => 'active',
                'access_panel' => GuardNameEnum::SELLER->value,
                'logged_in_type' => 'platform',
                'country' => 'Bangladesh',
                'iso_2' => 'BD',
                'country_code' => '+880',
            ]);

            $role = Role::findOrCreate('seller', GuardNameEnum::SELLER->value);
            $user->syncRoles([$role]);

            $seller = Seller::query()->create([
                'user_id' => $user->id,
                'business_name' => $data['business_name'],
                'legal_name' => $data['legal_name'] ?? null,
                'trade_license_number' => $data['trade_license_number'] ?? null,
                'tax_number' => $data['tax_number'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => 'Bangladesh',
                'country_code' => '+880',
                'verification_status' => 'pending',
                'visibility_status' => 'draft',
                'status' => 'active',
            ]);

            if (! empty($data['store_name'])) {
                Store::query()->create([
                    'seller_id' => $seller->id,
                    'name' => $data['store_name'],
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'contact_email' => $data['email'],
                    'contact_number' => $data['mobile'],
                    'country' => 'Bangladesh',
                    'country_code' => '+880',
                    'currency_code' => 'BDT',
                    'status' => 'offline',
                    'verification_status' => 'pending',
                    'visibility_status' => 'draft',
                ]);
            }

            $this->wallets->wallet($user, 'seller');

            return [$user, $seller];
        });

        Auth::guard('seller')->login($user);
        $request->session()->regenerate();
        $request->session()->put('seller_id', $seller->id);

        return redirect()->route('seller.dashboard')->with(
            'success',
            'Seller registration submitted. You can prepare your catalogue while verification is pending.'
        );
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('seller')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('seller.login')->with('success', 'You have been signed out.');
    }

    public function showForgotPassword(): View
    {
        return view('seller.auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $user = User::query()
            ->where('access_panel', GuardNameEnum::SELLER->value)
            ->where('status', 'active')
            ->where('email', $data['email'])
            ->first();

        if ($user) {
            $token = Password::broker('users')->createToken($user);
            $user->notify(new SellerResetPasswordNotification($token));
        }

        return back()->with('status', 'When an active seller account matches that email, a reset link will be sent.');
    }

    public function showResetPassword(Request $request, string $token): View
    {
        return view('seller.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $sellerUser = User::query()
            ->where('access_panel', GuardNameEnum::SELLER->value)
            ->where('status', 'active')
            ->where('email', $data['email'])
            ->first();

        if (! $sellerUser) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'The reset link is invalid or expired.',
            ]);
        }

        $status = Password::broker('users')->reset(
            $data,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'The reset link is invalid or expired.',
            ]);
        }

        return redirect()->route('seller.login')->with('success', 'Your seller password has been reset.');
    }
}
