<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemRelease extends Model
{
    protected $fillable = [
        'version','status','checksum','release_notes',
        'deployed_by','deployed_at','metadata',
    ];

    protected function casts(): array
    {
        return [
            'deployed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
