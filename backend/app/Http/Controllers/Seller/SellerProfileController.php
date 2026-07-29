<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Seller;
use App\Models\User;
use App\Services\Seller\SellerPanelContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SellerProfileController extends Controller
{
    public function __construct(
        private readonly SellerPanelContext $context
    ) {
    }

    public function edit(Request $request): View
    {
        $user = Auth::guard('seller')->user();
        $seller = $request->attributes->get('seller')
            ?? $this->context->resolve($user);

        return view('seller.profile', compact('user', 'seller'));
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('seller')->user();
        /** @var Seller $seller */
        $seller = $request->attributes->get('seller')
            ?? $this->context->resolve($user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'mobile' => ['nullable', 'string', 'max:32', 'unique:users,mobile,'.$user->id],
            'business_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'trade_license_number' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
        ]);

        $seller->update(collect($data)->except(['name', 'email', 'mobile'])->all());

        return back()->with('success', 'Seller profile updated successfully.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('seller')->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password:seller'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user->update(['password' => Hash::make($data['password'])]);

        return back()->with('success', 'Password updated successfully.');
    }
}
