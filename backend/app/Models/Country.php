<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'iso3',
        'iso2',
        'numeric_code',
        'phonecode',
        'capital',
        'currency',
        'currency_name',
        'currency_symbol',
        'tld',
        'native',
        'region',
        'subregion',
        'timezones',
        'translations',
        'latitude',
        'longitude',
        'emoji',
        'emojiU',
        'flag',
        'wikiDataId',
    ];

    protected function casts(): array
    {
        return [
            'timezones' => 'array',
            'translations' => 'array',
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'flag' => 'boolean',
        ];
    }
}