<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'variable';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'variable',
        'value',
    ];

    public function getValueAttribute(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    public function setValueAttribute(array|string|null $value): void
    {
        $this->attributes['value'] = is_array($value)
            ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            : ($value ?? '{}');
    }
}