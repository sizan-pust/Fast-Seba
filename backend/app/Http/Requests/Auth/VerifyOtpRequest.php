<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'mobile' => ['required', 'string', 'max:32'],
            'otp' => ['required', 'string', 'size:6'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
            'country' => ['nullable', 'string', 'max:255'],
            'iso_2' => ['nullable', 'string', 'size:2'],
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                'exists:users,referral_code',
            ],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
        ];
    }
}