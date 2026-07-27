<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class GoogleCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idToken' => ['required', 'string'],
            'friends_code' => [
                'nullable',
                'string',
                'max:32',
                'exists:users,referral_code',
            ],
            'fcm_token' => ['nullable', 'string', 'max:255'],
            'device_type' => ['nullable', 'in:android,ios,web'],
            'country' => ['nullable', 'string', 'max:255'],
            'iso_2' => ['nullable', 'string', 'size:2'],
        ];
    }
}