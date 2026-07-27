<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $online = ['razorpayPayment','stripePayment','paystackPayment','flutterwavePayment'];

        return [
            'payment_type' => ['required', Rule::in(['cod','wallet','offline', ...$online])],
            'promo_code' => ['nullable','string','max:100'],
            'gift_card' => ['nullable','string','max:100'],
            'address_id' => ['required_unless:delivery_type,pickup','nullable','integer','exists:addresses,id'],
            'delivery_type' => ['required', Rule::in(['delivery','pickup'])],
            'rush_delivery' => ['nullable','boolean'],
            'use_wallet' => ['nullable','boolean'],
            'order_note' => ['nullable','string','max:500'],
            'transaction_id' => [Rule::requiredIf(fn () => in_array($this->input('payment_type'), $online, true)),'nullable','string','max:255'],
            'payment_details' => ['nullable','array'],
        ];
    }
}
