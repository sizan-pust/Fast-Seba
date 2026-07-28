<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGatewayConfig extends Model
{
    protected $fillable = [
        'code','display_name','enabled','test_mode','sort_order',
        'public_config','secret_config','supported_currencies','metadata',
    ];

    protected $hidden = ['secret_config'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'test_mode' => 'boolean',
            'sort_order' => 'integer',
            'public_config' => 'array',
            'secret_config' => 'encrypted:array',
            'supported_currencies' => 'array',
            'metadata' => 'array',
        ];
    }
}
