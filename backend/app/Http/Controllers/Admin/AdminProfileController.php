<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminProfileController extends Controller
{
    public function edit(): View
    {
        return view(
            'admin.profile',
            [
                'admin' => Auth::guard('admin')->user(),
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $user = Auth::guard('admin')->user();

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'mobile' => [
                'nullable',
                'string',
                'max:30',
            ],
            'country' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $user->update($data);

        return back()->with(
            'success',
            'Admin profile updated.'
        );
    }

    public function updatePassword(
        Request $request
    ): RedirectResponse {
        $user = Auth::guard('admin')->user();

        $data = $request->validate([
            'current_password' => [
                'required',
                'string',
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers(),
            ],
        ]);

        if (
            ! Hash::check(
                $data['current_password'],
                $user->password
            )
        ) {
            return back()->withErrors([
                'current_password' =>
                    'The current password is incorrect.',
            ]);
        }

        $user->update([
            'password' => $data['password'],
        ]);

        return back()->with(
            'success',
            'Admin password updated.'
        );
    }
}
